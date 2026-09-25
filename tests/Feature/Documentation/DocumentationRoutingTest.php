<?php

declare(strict_types=1);

use App\Models\DocumentationPage;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| The version segment and the first page segment occupy the same
| position, so almost everything here is about telling them apart — and the
| rule is the registry's answer, never a pattern match against the segment.
*/

beforeEach(function (): void {
    Storage::fake('documentation');
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
});

it('serves a page at its canonical versioned URL', function (): void {
    publishDocumentation(['index' => "# Example\n", 'installation' => "# Installation\n\nRun it.\n"]);

    $this->get('/example/1.x/installation')
        ->assertSuccessful()
        ->assertSee('Run it.');
});

it('serves a nested page', function (): void {
    publishDocumentation(['index' => "# Example\n", 'architecture/boundaries' => "# Boundaries\n\nThe line.\n"]);

    $this->get('/example/1.x/architecture/boundaries')
        ->assertSuccessful()
        ->assertSee('The line.');
});

it('serves index at the version root', function (): void {
    publishDocumentation(['index' => "# Example\n\nThe shared foundation.\n"]);

    $this->get('/example/1.x')
        ->assertSuccessful()
        ->assertSee('The shared foundation.');
});

it('redirects an unversioned page to the default version', function (): void {
    publishDocumentation(['index' => "# Example\n", 'installation' => "# Installation\n"]);

    $this->get('/example/installation')
        ->assertRedirect('/example/1.x/installation')
        ->assertMovedPermanently();
});

it('redirects an unversioned nested page', function (): void {
    publishDocumentation(['index' => "# Example\n", 'architecture/boundaries' => "# Boundaries\n"]);

    $this->get('/example/architecture/boundaries')
        ->assertRedirect('/example/1.x/architecture/boundaries');
});

it('redirects a project root to its default version', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $this->get('/example')
        ->assertRedirect('/example/1.x');
});

it('redirects rather than rendering the same page at two URLs', function (): void {
    publishDocumentation(['index' => "# Example\n", 'installation' => "# Installation\n"]);

    // Were this rendered in place, the URL would silently change
    // meaning the day a new version became the default.
    $this->get('/example/installation')
        ->assertStatus(301);
});

it('treats a segment as a version only when the registry says so', function (): void {
    $version = publishDocumentation(['index' => "# Example\n", 'installation' => "# Installation\n"]);

    // `next` is not registered, so it is a page path — and there is no such
    // page, so it is a 404 rather than an empty version.
    $this->get('/example/next/installation')
        ->assertRedirect('/example/1.x/next/installation');

    expect($version->project->versions()->where('version', 'next')->exists())->toBeFalse();
});

it('resolves a version whose name no pattern would guess', function (): void {
    publishDocumentation(['index' => "# Example\n"], version: 'next');

    $this->get('/example/next')
        ->assertSuccessful();
});

it('serves a rolling project with no version segment', function (): void {
    publishDocumentation(
        ['index' => "# Toolkit\n", 'operations/deploys' => "# Deploys\n\nHow to ship.\n"],
        slug: 'toolkit',
        version: 'main',
        rolling: true
    );

    $this->get('/toolkit/operations/deploys')
        ->assertSuccessful()
        ->assertSee('How to ship.');
});

it('does not read a version segment on a rolling project', function (): void {
    publishDocumentation(
        ['index' => "# Toolkit\n", 'main/notes' => "# Notes\n\nA page called main.\n"],
        slug: 'toolkit',
        version: 'main',
        rolling: true
    );

    // For a rolling project the whole remainder is a page path, even when its
    // first segment happens to equal the internal version name.
    $this->get('/toolkit/main/notes')
        ->assertSuccessful()
        ->assertSee('A page called main.');
});

it('resolves relative links in a served page', function (): void {
    publishDocumentation([
        'index' => "# Example\n\n[Install](installation.md)\n",
        'installation' => "# Installation\n",
    ]);

    $this->get('/example/1.x')
        ->assertSee('href="/example/1.x/installation"', escape: false);
});

it('honours a front-matter slug in both the URL and the links to it', function (): void {
    $version = publishDocumentation(['index' => "# Example\n\n[SSO](architecture/sso-integration.md)\n"]);

    File::ensureDirectoryExists(scratchRoot().'/docs-hub-routing/slugged/docs/architecture');

    DocumentationPage::factory()->for($version, 'version')->create([
        'slug' => 'architecture/sso',
        'source_path' => 'docs/architecture/sso-integration.md',
    ]);

    $this->get('/example/1.x')
        ->assertSee('href="/example/1.x/architecture/sso"', escape: false);
});

/*
| What it refuses.
*/

it('returns a 404 for a page that does not exist', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $this->get('/example/1.x/nowhere')->assertNotFound();
});

it('returns a 404 for a project that is not registered', function (): void {
    $this->get('/nonesuch/1.x/installation')->assertNotFound();
});

it('returns a 404 for a version that has never published', function (): void {
    $project = Project::factory()->create(['slug' => 'example']);
    ProjectVersion::factory()->for($project)->default()->create(['version' => '1.x']);

    $this->get('/example/1.x')->assertNotFound();
});

it('surfaces a project with no default version as a configuration error', function (): void {
    $project = Project::factory()->create(['slug' => 'example']);
    ProjectVersion::factory()->for($project)->create(['version' => '1.x']);

    // This should be visible to whoever can fix it, not hidden behind
    // something that reads as an ordinary missing page.
    $this->get('/example/installation')
        ->assertServerError();
});

it('serves documentation to a guest', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $this->get('/example/1.x')->assertSuccessful();
});

it('leaves the panel and other reserved paths to their owners', function (): void {
    Project::factory()->create(['slug' => 'assets']);

    $this->get('/assets')->assertNotFound();

    $this->actingAs(User::factory()->create())->get('/admin')->assertSuccessful();
});

/*
| The repository-owned redirect map.
*/

it('follows a redirect the manifest declares', function (): void {
    $version = publishDocumentation(['index' => "# Example\n", 'installation' => "# Installation\n"]);
    $version->update(['redirects' => ['getting-started' => 'installation']]);

    $this->get('/example/1.x/getting-started')
        ->assertRedirect('/example/1.x/installation')
        ->assertMovedPermanently();
});

it('follows a redirect to a page in another directory', function (): void {
    $version = publishDocumentation([
        'index' => "# Example\n",
        'architecture/sso-integration' => "# SSO\n",
    ]);
    $version->update(['redirects' => ['sso-integration' => 'architecture/sso-integration']]);

    $this->get('/example/1.x/sso-integration')
        ->assertRedirect('/example/1.x/architecture/sso-integration');
});

it('does not follow a redirect that leads nowhere', function (): void {
    $version = publishDocumentation(['index' => "# Example\n"]);
    $version->update(['redirects' => ['old' => 'also-gone']]);

    $this->get('/example/1.x/old')->assertNotFound();
});

it('gives up rather than looping on a circular redirect', function (): void {
    $version = publishDocumentation(['index' => "# Example\n"]);
    $version->update(['redirects' => ['a' => 'b', 'b' => 'a']]);

    $this->get('/example/1.x/a')->assertNotFound();
});

it('prefers a real page over a redirect of the same name', function (): void {
    $version = publishDocumentation(['index' => "# Example\n", 'installation' => "# Installation\n"]);
    $version->update(['redirects' => ['installation' => 'index']]);

    $this->get('/example/1.x/installation')
        ->assertSuccessful();
});
