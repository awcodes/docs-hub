<?php

declare(strict_types=1);

use App\Documentation\Actions\SyncProjectVersion;
use App\Documentation\Data\SyncOutcome;
use App\Documentation\Exceptions\SyncFailed;
use App\Documentation\Storage\SnapshotStore;
use App\Enums\DocumentationType;
use App\Models\DocumentationPage;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| The ordering claim is the one worth testing hardest: everything
| before the pointer update happens on a snapshot nobody is reading, so a sync
| that fails anywhere leaves yesterday's documentation serving.
*/

beforeEach(function (): void {
    Storage::fake('documentation');
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
    File::deleteDirectory(storage_path('app/docs-staging'));
});

function sync(ProjectVersion $version, bool $force = false): App\Documentation\Data\SyncResult
{
    return (new SyncProjectVersion)->handle($version, $force);
}

it('publishes a structured documentation set', function (): void {
    $version = mounted([
        'docs/docs.yml' => "title: Example Package\nnavigation:\n  - index\n  - installation",
        'docs/index.md' => "---\ntitle: Example\n---\n\n# Example\n\n## Overview\n",
        'docs/installation.md' => "# Installation\n",
        'README.md' => "# acme/example\n",
    ]);

    $result = sync($version);

    expect($result->outcome)->toBe(SyncOutcome::Published)
        ->and($result->documentationType)->toBe(DocumentationType::Structured)
        ->and($result->pagesIndexed)->toBe(2)
        ->and($version->refresh()->isPublished())->toBeTrue()
        ->and($version->active_snapshot)->toBe($result->commit)
        ->and($version->last_synced_at)->not->toBeNull();
});

it('indexes each page with the metadata a reader needs', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => <<<'MD'
            ---
            title: Theme composition
            description: The contract every package follows.
            ---

            # Theme composition

            ## One filename, three jobs
            MD,
    ]);

    sync($version);

    $page = $version->pages()->firstOrFail();

    expect($page->slug)->toBe('index')
        ->and($page->source_path)->toBe('docs/index.md')
        ->and($page->title)->toBe('Theme composition')
        ->and($page->description)->toBe('The contract every package follows.')
        ->and($page->headings)->toContain([
            'level' => 2,
            'title' => 'One filename, three jobs',
            'anchor' => 'one-filename-three-jobs',
        ]);
});

it('indexes a page under the slug its front matter claims', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index\n  - architecture/sso-integration",
        'docs/index.md' => "# Example\n",
        'docs/architecture/sso-integration.md' => "---\nslug: sso\n---\n\n# SSO\n",
    ]);

    sync($version);

    expect($version->pages()->pluck('slug')->all())->toContain('architecture/sso')
        ->and($version->pages()->where('slug', 'architecture/sso')->value('source_path'))
        ->toBe('docs/architecture/sso-integration.md');
});

it('indexes a page that no navigation entry lists', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
        'docs/half-written.md' => "# Not in the sidebar\n",
    ]);

    sync($version);

    expect($version->pages()->pluck('slug')->all())->toContain('half-written');
});

it('falls back to the README when there is no docs directory', function (): void {
    $version = mounted(['README.md' => "# A small tool\n\nWhat it does.\n"]);

    $result = sync($version);

    expect($result->documentationType)->toBe(DocumentationType::Readme)
        ->and($result->pagesIndexed)->toBe(1);

    $page = $version->pages()->firstOrFail();

    expect($page->slug)->toBe('index')
        ->and($page->source_path)->toBe('README.md');
});

it('promotes a README-backed version when a docs directory appears', function (): void {
    $version = mounted(['README.md' => "# A small tool\n"]);

    expect(sync($version)->documentationType)->toBe(DocumentationType::Readme);

    config()->set('documentation.local_sources', [
        'example' => checkout('example', [
            'README.md' => "# A small tool\n",
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# A small tool\n",
        ]),
    ]);

    expect(sync($version)->documentationType)->toBe(DocumentationType::Structured);
});

it('stores the manifest redirect map beside the page index', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index\n  - installation\nredirects:\n  getting-started: installation",
        'docs/index.md' => "# Example\n",
        'docs/installation.md' => "# Installation\n",
    ]);

    sync($version);

    expect($version->refresh()->redirects)->toBe(['getting-started' => 'installation']);
});

/*
| Doing nothing, and doing nothing destructive.
*/

it('does nothing when the commit has not moved', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $first = sync($version);
    $second = sync($version->refresh());

    expect($second->outcome)->toBe(SyncOutcome::Unchanged)
        ->and($second->commit)->toBe($first->commit);
});

it('republishes an unchanged commit when forced', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    sync($version);

    expect(sync($version->refresh(), force: true)->outcome)->toBe(SyncOutcome::Published);
});

it('keeps serving the last good snapshot when validation fails', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n\nThe good version.\n",
    ]);

    $good = sync($version);

    config()->set('documentation.local_sources', [
        'example' => checkout('example', [
            'docs/docs.yml' => "navigation:\n  - index\n  - instalation",
            'docs/index.md' => "# Example\n\nThe broken version.\n",
        ]),
    ]);

    expect(fn (): mixed => sync($version->refresh()))
        ->toThrow(SyncFailed::class, 'did not pass validation');

    $version->refresh();

    expect($version->active_snapshot)->toBe($good->commit)
        ->and((new SnapshotStore)->readPublished($version, 'docs/index.md'))
        ->toContain('The good version.');
});

it('refuses a checkout with no documentation at all', function (): void {
    $version = mounted(['composer.json' => '{}']);

    sync($version);
})->throws(SyncFailed::class, 'neither a documentation directory nor a README');

it('leaves no staging directory behind, even after a failure', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - nowhere",
        'docs/index.md' => "# Example\n",
    ]);

    try {
        sync($version);
    } catch (SyncFailed) {
        // Expected.
    }

    expect(File::directories(storage_path('app/docs-staging')))->toBeEmpty();
});

/*
| Pruning.
*/

it('stops serving a page that was deleted upstream', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index\n  - obsolete",
        'docs/index.md' => "# Example\n",
        'docs/obsolete.md' => "# Obsolete\n",
    ]);

    sync($version);

    expect($version->pages()->count())->toBe(2);

    config()->set('documentation.local_sources', [
        'example' => checkout('example', [
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# Example\n",
        ]),
    ]);

    $result = sync($version->refresh());

    expect($result->pagesRemoved)->toBe(1)
        ->and($version->pages()->pluck('slug')->all())->toBe(['index']);
});

it('keeps the identity of a page that survived a sync', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    sync($version);
    $id = $version->pages()->value('id');

    config()->set('documentation.local_sources', [
        'example' => checkout('example', [
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# Example\n\nNow with a paragraph.\n",
        ]),
    ]);

    sync($version->refresh());

    // Search rows will hang off these records, so churning the ids on every
    // sync would mean reindexing documentation that did not change.
    expect($version->pages()->value('id'))->toBe($id);
});

it('prunes superseded snapshots but keeps a rollback target', function (): void {
    config()->set('documentation.snapshots.retain', 2);

    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# One\n",
    ]);

    foreach (['Two', 'Three', 'Four'] as $body) {
        sync($version->refresh());

        config()->set('documentation.local_sources', [
            'example' => checkout('example', [
                'docs/docs.yml' => "navigation:\n  - index",
                'docs/index.md' => "# {$body}\n",
            ]),
        ]);
    }

    sync($version->refresh());

    expect((new SnapshotStore)->snapshots($version))->toHaveCount(2);
});

it('keeps one version\'s pages out of another\'s', function (): void {
    $one = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# One\n",
    ]);

    $two = ProjectVersion::factory()->for($one->project)->create(['version' => '2.x']);

    sync($one);
    sync($two);

    expect($one->pages()->count())->toBe(1)
        ->and($two->pages()->count())->toBe(1)
        ->and(DocumentationPage::query()->count())->toBe(2);
});

it('publishes Example\'s real documentation unchanged', function (): void {
    $project = Project::factory()->create(['slug' => 'example']);
    $version = ProjectVersion::factory()->for($project)->default()->create(['version' => '1.x']);

    config()->set('documentation.local_sources', [
        'example' => base_path('tests/fixtures/example'),
    ]);

    $result = sync($version);

    // A real package's `docs/` publishes with no change to that repository.
    expect($result->documentationType)->toBe(DocumentationType::Structured)
        ->and($result->pagesIndexed)->toBe(11)
        ->and($version->refresh()->isPublished())->toBeTrue()
        ->and($version->pages()->pluck('slug')->all())
        ->toContain('index', 'installation', 'usage/authentication', 'architecture/theme-composition');
});

/*
| The stored manifest.
|
| The page index knows every page. Only `docs.yml` knows the order, the groups
| and the labels, which is the whole reason this is stored rather than derived.
*/

it('stores the navigation tree the manifest described', function (): void {
    $version = mounted([
        'docs/docs.yml' => <<<'YAML'
            title: Example Package
            navigation:
              - index
              - installation
              - label: Usage
                children:
                  - usage/authentication
                  - usage/permissions
            YAML,
        'docs/index.md' => "# Example\n",
        'docs/installation.md' => "# Installation\n",
        'docs/usage/authentication.md' => "# Authentication\n",
        'docs/usage/permissions.md' => "# Permissions\n",
    ]);

    sync($version);

    expect($version->refresh()->navigation)->toBe([
        ['page' => 'index'],
        ['page' => 'installation'],
        ['label' => 'Usage', 'children' => ['usage/authentication', 'usage/permissions']],
    ])->and($version->manifest_title)->toBe('Example Package');
});

it('stores navigation against the URL a page is served at', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index\n  - architecture/sso-integration",
        'docs/index.md' => "# Example\n",
        'docs/architecture/sso-integration.md' => "---\nslug: sso\n---\n\n# SSO\n",
    ]);

    sync($version);

    // The manifest names a file; a reader follows a URL. Resolving once here
    // keeps the sidebar a lookup rather than a second resolution pass.
    expect($version->refresh()->navigation)->toBe([
        ['page' => 'index'],
        ['page' => 'architecture/sso'],
    ]);
});

it('keeps the registry name and the manifest title apart', function (): void {
    $version = mounted([
        'docs/docs.yml' => "title: What The Manifest Calls It\nnavigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $version->project->update(['name' => 'What The Registry Calls It']);

    sync($version);

    // The registry wins for anything a reader sees, because hub
    // chrome must stay consistent across versions whose manifests disagree.
    expect($version->refresh()->manifest_title)->toBe('What The Manifest Calls It')
        ->and($version->project->name)->toBe('What The Registry Calls It');
});

it('leaves a README-backed version with no navigation', function (): void {
    $version = mounted(['README.md' => "# A small tool\n"]);

    sync($version);

    expect($version->refresh()->navigation)->toBeNull();
});

it('drops navigation when a project falls back to its README', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
        'README.md' => "# Example\n",
    ]);

    sync($version);
    expect($version->refresh()->navigation)->not->toBeNull();

    config()->set('documentation.local_sources', [
        'example' => checkout('example', ['README.md' => "# Example\n"]),
    ]);

    sync($version->refresh());

    // Stale navigation would outlive the manifest that justified it.
    expect($version->refresh()->navigation)->toBeNull();
});

it('stores Example\'s real navigation, groups and all', function (): void {
    $project = Project::factory()->create(['slug' => 'example']);
    $version = ProjectVersion::factory()->for($project)->default()->create(['version' => '1.x']);

    config()->set('documentation.local_sources', [
        'example' => base_path('tests/fixtures/example'),
    ]);

    sync($version);

    $navigation = $version->refresh()->navigation;
    $labels = array_values(array_filter(array_column($navigation, 'label')));

    expect($navigation[0])->toBe(['page' => 'index'])
        ->and($labels)->toBe(['Usage', 'Architecture', 'Contributing'])
        ->and((new App\Documentation\Navigation\NavigationTree)->readingOrder($version))
        ->toHaveCount(11);
});

/*
| Search indexing.
*/

it('indexes each page as sections while syncing', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => <<<'MD'
            # Theme composition

            The contract every package follows.

            ## One filename, three jobs

            Position in the chain decides what that file does.
            MD,
    ]);

    sync($version);

    expect(App\Models\DocumentationSection::query()->count())->toBe(2);
});

it('stops a deleted page appearing in search', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index\n  - obsolete",
        'docs/index.md' => "# Example\n",
        'docs/obsolete.md' => "# Obsolete\n\nA unique phrase nobody else uses.\n",
    ]);

    sync($version);

    expect((new App\Documentation\Search\DocumentationSearch)->search('unique phrase'))->toHaveCount(1);

    config()->set('documentation.local_sources', [
        'example' => checkout('example', [
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# Example\n",
        ]),
    ]);

    sync($version->refresh());

    // Acceptance criterion 19: a page deleted upstream stops resolving *and*
    // disappears from search after the next sync.
    expect((new App\Documentation\Search\DocumentationSearch)->search('unique phrase'))->toBeEmpty();
});

it('re-indexes a page rather than accumulating its old sections', function (): void {
    $version = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n\nIntro.\n\n## First\n\nOne.\n\n## Second\n\nTwo.\n",
    ]);

    sync($version);
    expect(App\Models\DocumentationSection::query()->count())->toBe(3);

    config()->set('documentation.local_sources', [
        'example' => checkout('example', [
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# Example\n\nIntro.\n\n## Only one now\n\nOne.\n",
        ]),
    ]);

    sync($version->refresh());

    expect(App\Models\DocumentationSection::query()->count())->toBe(2);
});

it('indexes Example\'s real documentation into searchable sections', function (): void {
    $project = Project::factory()->create(['slug' => 'example']);
    $version = ProjectVersion::factory()->for($project)->default()->create([
        'version' => '1.x',
        'status' => App\Enums\VersionStatus::Current,
    ]);

    config()->set('documentation.local_sources', [
        'example' => base_path('tests/fixtures/example'),
    ]);

    sync($version);

    $results = (new App\Documentation\Search\DocumentationSearch)->search('theme composition');

    expect(App\Models\DocumentationSection::query()->count())->toBeGreaterThan(40)
        ->and($results)->not->toBeEmpty()
        ->and($results[0]->pageTitle)->toBe('Theme composition');
});
