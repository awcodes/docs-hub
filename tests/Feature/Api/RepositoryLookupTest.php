<?php

declare(strict_types=1);

use App\Enums\VersionStatus;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| aw.codes asks this endpoint whether a repository is documented here, and
| embeds the answer on its project pages. A 404 is a real answer — "not
| documented here" — so it must only ever mean that.
*/

beforeEach(function (): void {
    Storage::fake('documentation');
    config()->set('app.url', 'https://docs.example.test');
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
});

it('describes a documented repository', function (): void {
    $version = publishDocumentation(['index' => "# Example\n\nThe shared foundation.\n"]);
    $project = $version->project;
    $version->forceFill(['git_ref' => '1.x', 'last_synced_at' => '2026-10-02 09:00:00'])->save();

    ProjectVersion::factory()->for($project)->published()->status(VersionStatus::Legacy)->create(['version' => '0.x']);

    $this->getJson("/api/repositories/{$project->repository}")
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertJsonPath('data.slug', 'example')
        ->assertJsonPath('data.repository', $project->repository)
        ->assertJsonPath('data.versioning_mode', 'versioned')
        ->assertJsonPath('data.docs_url', 'https://docs.example.test/example/1.x')
        ->assertJsonPath('data.default_version.version', '1.x')
        ->assertJsonPath('data.default_version.git_ref', '1.x')
        ->assertJsonPath('data.default_version.status', 'current')
        ->assertJsonPath('data.default_version.last_synced_at', '2026-10-02T09:00:00+00:00')
        ->assertJsonPath('data.versions', [
            ['version' => '1.x', 'status' => 'current', 'is_default' => true, 'url' => 'https://docs.example.test/example/1.x'],
            ['version' => '0.x', 'status' => 'legacy', 'is_default' => false, 'url' => 'https://docs.example.test/example/0.x'],
        ])
        ->assertJsonPath('data.index.title', 'Example')
        ->assertJsonPath('data.index.description', $project->description);
});

it('answers 404 for a repository the hub does not document', function (): void {
    $this->getJson('/api/repositories/acme/unknown')->assertNotFound();
});

it('answers 404 for a hidden project', function (): void {
    $project = Project::factory()->hidden()->create();

    $this->getJson("/api/repositories/{$project->repository}")->assertNotFound();
});

it('matches the repository case-insensitively', function (): void {
    Project::factory()->create(['repository' => 'awcodes/Matinee']);

    $this->getJson('/api/repositories/AWCODES/matinee')
        ->assertSuccessful()
        ->assertJsonPath('data.repository', 'awcodes/Matinee');
});

it('omits the version segment for a rolling project', function (): void {
    $version = publishDocumentation(['index' => "# Toolkit\n"], slug: 'toolkit', version: 'main', rolling: true);

    $this->getJson("/api/repositories/{$version->project->repository}")
        ->assertJsonPath('data.docs_url', 'https://docs.example.test/toolkit')
        ->assertJsonPath('data.versions.0.url', 'https://docs.example.test/toolkit');
});

it('leaves unpublished versions out of the list', function (): void {
    $version = publishDocumentation(['index' => "# Example\n"]);
    ProjectVersion::factory()->for($version->project)->create(['version' => '2.x']);

    $this->getJson("/api/repositories/{$version->project->repository}")
        ->assertJsonCount(1, 'data.versions');
});

it('drops the opening heading from the index', function (): void {
    $version = publishDocumentation(['index' => "# Example\n\nThe shared foundation.\n\n# Later\n"]);

    $html = $this->getJson("/api/repositories/{$version->project->repository}")->json('data.index.html');

    expect($html)
        ->not->toContain('Example</h1>')
        ->toStartWith('<p>The shared foundation.</p>')
        ->toContain('<h1');
});

it('makes links and assets absolute, keeping fragments', function (): void {
    $version = publishDocumentation([
        'index' => "# Example\n\n[Install](installation.md#requirements) and ![Editor](assets/editor-dark.png#gh-dark-mode-only)\n\n[Elsewhere](https://github.com/acme)\n",
        'installation' => "# Installation\n",
    ]);

    $html = $this->getJson("/api/repositories/{$version->project->repository}")->json('data.index.html');

    expect($html)
        ->toContain('href="https://docs.example.test/example/1.x/installation#requirements"')
        ->toContain('src="https://docs.example.test/assets/example/1.x/editor-dark.png#gh-dark-mode-only"')
        ->toContain('href="https://github.com/acme"');
});

it('returns a null index until the default version is published', function (): void {
    $project = Project::factory()->create();
    ProjectVersion::factory()->for($project)->default()->create();

    $this->getJson("/api/repositories/{$project->repository}")
        ->assertSuccessful()
        ->assertJsonPath('data.index', null)
        ->assertJsonPath('data.versions', []);
});

it('returns a null index when the default version has no index page', function (): void {
    $version = publishDocumentation(['installation' => "# Installation\n"]);

    $this->getJson("/api/repositories/{$version->project->repository}")
        ->assertJsonPath('data.index', null);
});

it('is rate limited', function (): void {
    $project = Project::factory()->create();

    foreach (range(1, 60) as $attempt) {
        $this->getJson("/api/repositories/{$project->repository}");
    }

    $this->getJson("/api/repositories/{$project->repository}")->assertTooManyRequests();
});
