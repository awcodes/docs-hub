<?php

declare(strict_types=1);

use App\Enums\VersionStatus;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| Switching version is not a URL substitution: a page
| that exists on 1.x may never have been written on 2.x, and the reader should
| land on the 2.x root rather than a 404.
*/

beforeEach(function (): void {
    Storage::fake('documentation');
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
});

/** A second version of an already-published project. */
function alsoPublished(Project $project, string $version, array $pages, VersionStatus $status = VersionStatus::Supported): ProjectVersion
{
    $record = ProjectVersion::factory()->for($project)->create([
        'version' => $version,
        'status' => $status,
    ]);

    $root = documentationScratch().'/'.$project->slug.'-'.$version;

    foreach ($pages as $reference => $markdown) {
        File::ensureDirectoryExists(dirname("{$root}/docs/{$reference}.md"));
        File::put("{$root}/docs/{$reference}.md", $markdown);

        App\Models\DocumentationPage::factory()->for($record, 'version')->create([
            'slug' => $reference,
            'source_path' => "docs/{$reference}.md",
        ]);
    }

    $commit = mb_substr(hash('sha256', $project->slug.$version), 0, 12);
    (new App\Documentation\Storage\SnapshotStore)->write($record, $commit, $root);
    (new App\Documentation\Actions\PublishSnapshot)->handle($record, $commit);

    $record->forceFill([
        'navigation' => array_map(
            static fn (string $reference): array => ['page' => $reference],
            array_keys($pages),
        ),
    ])->save();

    return $record->refresh();
}

it('keeps the reader on the equivalent page across versions', function (): void {
    $one = publishDocumentation([
        'index' => "# Example\n",
        'architecture/boundaries' => "# Boundaries\n",
    ]);

    alsoPublished($one->project, '2.x', [
        'index' => "# Example\n",
        'architecture/boundaries' => "# Boundaries\n",
    ]);

    $this->get('/example/1.x/architecture/boundaries')
        ->assertSee('/example/2.x/architecture/boundaries', escape: false);
});

it('falls back to the version root when the page has no equivalent', function (): void {
    $one = publishDocumentation([
        'index' => "# Example\n",
        'architecture/boundaries' => "# Boundaries\n",
    ]);

    alsoPublished($one->project, '2.x', ['index' => "# Example\n"]);

    $response = $this->get('/example/1.x/architecture/boundaries');

    // The link says where it will land, rather than pointing at a 404 and
    // recovering after the reader has already followed it.
    expect($response->getContent())
        ->toContain('href="/example/2.x"')
        ->not->toContain('/example/2.x/architecture/boundaries');
});

it('lists versions newest first, naturally', function (): void {
    $one = publishDocumentation(['index' => "# Example\n"], version: '2.x');
    alsoPublished($one->project, '10.x', ['index' => "# Example\n"]);
    alsoPublished($one->project, '1.x', ['index' => "# Example\n"]);

    $body = $this->get('/example/2.x')->getContent();

    // A plain string sort would put `10.x` below `1.x`.
    expect(mb_strpos($body, '/example/10.x'))->toBeLessThan(mb_strpos($body, '/example/2.x"'))
        ->and(mb_strpos($body, '/example/2.x"'))->toBeLessThan(mb_strpos($body, '/example/1.x'));
});

it('shows no version control when a project has only one', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $this->get('/example/1.x')
        ->assertSee('1.x')
        ->assertDontSee('Switch version');
});

it('shows no version control for a rolling project', function (): void {
    publishDocumentation(['index' => "# Toolkit\n"], slug: 'toolkit', version: 'main', rolling: true);

    $this->get('/toolkit')
        ->assertSuccessful()
        ->assertDontSee('Switch version');
});

it('offers every visible project, in its group', function (): void {
    publishDocumentation(['index' => "# Example\n"]);
    publishDocumentation(['index' => "# Toolkit\n"], slug: 'toolkit', version: 'main', rolling: true);

    Project::factory()->hidden()->create(['slug' => 'being-set-up', 'name' => 'Half Built']);

    $this->get('/example/1.x')
        ->assertSee('Switch project')
        ->assertSee('Packages')
        ->assertSee('Applications')
        ->assertSee('/toolkit', escape: false)
        ->assertDontSee('Half Built');
});

/*
| The legacy notice.
*/

it('tells a reader on legacy documentation where current is', function (): void {
    $legacy = publishDocumentation(['index' => "# Example\n"], version: '0.x');
    $legacy->update(['status' => VersionStatus::Legacy]);

    alsoPublished($legacy->project, '1.x', ['index' => "# Example\n"], VersionStatus::Current);

    $this->get('/example/0.x')
        ->assertSee('You are viewing')
        ->assertSee('0.x documentation')
        ->assertSee('1.x is the current version');
});

it('does not warn a reader who is on the current version', function (): void {
    $version = publishDocumentation(['index' => "# Example\n"]);
    $version->update(['status' => VersionStatus::Current]);

    $this->get('/example/1.x')
        ->assertDontSee('You are viewing');
});

it('does not warn on a supported version that is simply not the newest', function (): void {
    $supported = publishDocumentation(['index' => "# Example\n"]);
    $supported->update(['status' => VersionStatus::Supported]);

    alsoPublished($supported->project, '2.x', ['index' => "# Example\n"], VersionStatus::Current);

    // Supported is not legacy. Reading it is not a mistake to be corrected.
    $this->get('/example/1.x')
        ->assertDontSee('You are viewing');
});
