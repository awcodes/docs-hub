<?php

declare(strict_types=1);

use App\Documentation\Actions\PublishSnapshot;
use App\Documentation\Storage\SnapshotStore;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| Assets are served out of the version's published snapshot, from an
| allow-list of extensions, and never from outside the documentation root.
*/

beforeEach(function (): void {
    Storage::fake('documentation');
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
});

/** @param array<string, string> $files snapshot-relative path => contents */
function withAssets(array $files, string $slug = 'example', string $version = '1.x'): ProjectVersion
{
    $project = Project::factory()->create(['slug' => $slug]);
    $record = ProjectVersion::factory()->for($project)->default()->create(['version' => $version]);

    $root = documentationScratch().'/'.Str::random(12);

    File::ensureDirectoryExists("{$root}/docs");
    File::put("{$root}/docs/docs.yml", "navigation:\n  - index");
    File::put("{$root}/docs/index.md", "# Index\n");

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$root}/{$path}"));
        File::put("{$root}/{$path}", $contents);
    }

    $commit = mb_substr(hash('sha256', $slug.$version), 0, 12);

    (new SnapshotStore)->write($record, $commit, $root);
    (new PublishSnapshot)->handle($record, $commit);

    return $record->refresh();
}

it('serves an asset from the published snapshot', function (): void {
    withAssets(['docs/assets/panel-conventions.png' => 'PNGDATA']);

    $response = $this->get('/assets/example/1.x/panel-conventions.png')
        ->assertSuccessful();

    expect($response->streamedContent())->toBe('PNGDATA')
        ->and($response->headers->get('Content-Type'))->toBe('image/png');
});

it('serves an asset nested below the asset root', function (): void {
    withAssets(['docs/assets/diagrams/flow.svg' => '<svg></svg>']);

    $this->get('/assets/example/1.x/diagrams/flow.svg')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

it('serves the URL the renderer writes', function (): void {
    $version = withAssets(['docs/assets/panel.png' => 'PNGDATA']);

    $url = (new App\Documentation\Support\DocumentationUrl)
        ->asset($version->project, $version, 'panel.png');

    $this->get($url)->assertSuccessful();
});

it('serves assets to a guest', function (): void {
    withAssets(['docs/assets/panel.png' => 'PNGDATA']);

    $this->get('/assets/example/1.x/panel.png')->assertSuccessful();
});

/*
| Headers.
*/

it('caches publicly, but not as immutable', function (): void {
    withAssets(['docs/assets/panel.png' => 'PNGDATA']);

    $cacheControl = $this->get('/assets/example/1.x/panel.png')
        ->headers->get('Cache-Control');

    // The URL names a version, not a commit, so a later sync can change the
    // bytes behind it.
    expect($cacheControl)->toContain('public')
        ->and($cacheControl)->toContain('max-age=3600')
        ->and($cacheControl)->not->toContain('immutable');
});

it('denies a directly opened asset everything it could use', function (): void {
    withAssets(['docs/assets/flow.svg' => '<svg><script>alert(1)</script></svg>']);

    $this->get('/assets/example/1.x/flow.svg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
});

it('refuses a traversal that survives URL normalization', function (): void {
    withAssets([
        'docs/assets/panel.png' => 'PNGDATA',
        'docs/index.md' => "# Index\n",
    ]);

    // Encoded, so the route parameter really does arrive as `../index.md`
    // rather than being folded away before routing. This is the case the
    // store's own path guard exists for.
    $this->get('/assets/example/1.x/%2e%2e%2findex.md')
        ->assertNotFound();
});

it('refuses a traversal aimed at a servable extension', function (): void {
    withAssets([
        'docs/assets/panel.png' => 'PNGDATA',
        'docs/elsewhere.png' => 'NOTYOURS',
    ]);

    // The extension is one this route serves, so the only thing standing
    // between the request and `docs/elsewhere.png` is the path guard.
    $this->get('/assets/example/1.x/%2e%2e%2felsewhere.png')
        ->assertNotFound();
});
