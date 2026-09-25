<?php

declare(strict_types=1);

use App\Documentation\Search\DocumentationSearch;
use App\Enums\VersionStatus;
use App\Models\DocumentationSection;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| Cross-project search is the reason the hub exists rather than
| several separate documentation sites, so the first assertion is that one query
| reaches across projects at all.
*/

beforeEach(function (): void {
    Storage::fake('documentation');
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
});

function search(string $query, ...$arguments): array
{
    return (new DocumentationSearch)->search($query, ...$arguments);
}

it('searches across every project from one query', function (): void {
    publishDocumentation([
        'index' => "# Example\n",
        'theming' => "# Theming\n\nTailwind scanning is opt-in.\n",
    ]);

    publishDocumentation(
        ['index' => "# Toolkit\n\nToolkit also mentions Tailwind scanning.\n"],
        slug: 'toolkit',
        version: 'main',
        rolling: true,
    );

    $projects = array_unique(array_map(
        fn ($result): string => $result->projectName,
        search('tailwind scanning'),
    ));

    expect($projects)->toHaveCount(2);
});

it('indexes a page as its heading sections, not as one record', function (): void {
    $version = publishDocumentation(['index' => <<<'MD'
        # Theme composition

        The contract every package follows.

        ## One filename, three jobs

        Position in the chain decides what that file does.

        ## Scanning is opt-in

        A package declares its own sources.
        MD]);

    $sections = DocumentationSection::query()->where('project_version_id', $version->id)->get();

    // A page-level record dilutes relevance and tells a reader
    // nothing about where in a long page their term appears.
    expect($sections)->toHaveCount(3)
        ->and($sections->pluck('heading')->all())
        ->toBe([null, 'One filename, three jobs', 'Scanning is opt-in']);
});

it('deep-links a result to the heading it matched', function (): void {
    publishDocumentation(['index' => <<<'MD'
        # Theme composition

        ## One filename, three jobs

        Position in the chain decides everything.
        MD]);

    $result = search('position in the chain')[0];

    expect($result->url)->toBe('/example/1.x#one-filename-three-jobs')
        ->and($result->heading)->toBe('One filename, three jobs');
});

it('carries the breadcrumb a result needs to be understood', function (): void {
    publishDocumentation(['architecture/boundaries' => <<<'MD'
        # Boundaries

        ## Example presents, upstream packages persist

        The consequences are concrete.
        MD]);

    $result = search('consequences are concrete')[0];

    expect($result->projectName)->not->toBeEmpty()
        ->and($result->version)->toBe('1.x')
        ->and($result->pageTitle)->toBe('Boundaries')
        ->and($result->heading)->toBe('Example presents, upstream packages persist')
        ->and($result->excerpt)->toContain('consequences are concrete');
});

it('finds a term that only appears in code', function (): void {
    publishDocumentation(['index' => <<<'MD'
        # Authentication

        ## Switching it off

        ```php
        ExamplePlugin::make()->withoutAuthentication();
        ```
        MD]);

    // Stripping code blocks would make the terms most worth searching for the
    // ones that cannot be found.
    expect(search('withoutAuthentication'))->not->toBeEmpty();
});

it('requires every term rather than any', function (): void {
    publishDocumentation([
        'index' => "# Example\n\nSomething about theming.\n",
        'other' => "# Other\n\nSomething about permissions.\n",
    ]);

    expect(search('theming permissions'))->toBeEmpty()
        ->and(search('something theming'))->toHaveCount(1);
});

/*
| Relevance.
*/

it('ranks a title match above a body mention', function (): void {
    publishDocumentation([
        'index' => "# Example\n\nA passing mention of theming somewhere.\n",
        'theming' => "# Theming\n\nHow to contribute styles.\n",
    ]);

    expect(search('theming')[0]->pageTitle)->toBe('Theming');
});

it('boosts current documentation over supported and legacy', function (): void {
    $legacy = publishDocumentation(
        ['index' => "# Example\n\nThe theming contract.\n"],
        version: '0.x',
    );
    $legacy->update(['status' => VersionStatus::Legacy]);

    $current = publishDocumentation(
        ['index' => "# Example\n\nThe theming contract.\n"],
        slug: 'example-current',
        version: '1.x',
    );
    $current->update(['status' => VersionStatus::Current]);

    $results = search('theming contract');

    // A reader who searches without naming a version almost always means the
    // one in use.
    expect($results[0]->status)->toBe(VersionStatus::Current)
        ->and($results[1]->status)->toBe(VersionStatus::Legacy);
});

it('keeps legacy documentation searchable and marked', function (): void {
    $legacy = publishDocumentation(['index' => "# Example\n\nThe theming contract.\n"], version: '0.x');
    $legacy->update(['status' => VersionStatus::Legacy]);

    $result = search('theming contract')[0];

    expect($result->status->showsLegacyNotice())->toBeTrue();
});

/*
| Filtering and exclusions.
*/

it('narrows to one project', function (): void {
    publishDocumentation(['index' => "# Example\n\nShared vocabulary here.\n"]);
    publishDocumentation(
        ['index' => "# Toolkit\n\nShared vocabulary here.\n"],
        slug: 'toolkit',
        version: 'main',
        rolling: true,
    );

    $example = Project::query()->where('slug', 'example')->firstOrFail();

    expect(search('shared vocabulary'))->toHaveCount(2)
        ->and(search('shared vocabulary', $example))->toHaveCount(1);
});

it('narrows to one version', function (): void {
    $one = publishDocumentation(['index' => "# Example\n\nShared vocabulary here.\n"]);

    expect(search('shared vocabulary', null, $one))->toHaveCount(1);
});

it('does not return a project being set up', function (): void {
    $version = publishDocumentation(['index' => "# Example\n\nShared vocabulary here.\n"]);
    $version->project->update(['is_visible' => false]);

    expect(search('shared vocabulary'))->toBeEmpty();
});

it('does not return a version that has published nothing', function (): void {
    $version = publishDocumentation(['index' => "# Example\n\nShared vocabulary here.\n"]);
    $version->update(['active_snapshot' => null]);

    expect(search('shared vocabulary'))->toBeEmpty();
});

it('ignores a query too short to mean anything', function (): void {
    publishDocumentation(['index' => "# Example\n\nA page about a thing.\n"]);

    expect(search('a'))->toBeEmpty()
        ->and(search('  '))->toBeEmpty();
});

it('does not let a wildcard match everything', function (): void {
    publishDocumentation(['index' => "# Example\n\nA page about a thing.\n"]);

    expect(search('%'))->toBeEmpty()
        ->and(search('%%'))->toBeEmpty();
});

it('finds a heading that has no body of its own', function (): void {
    publishDocumentation(['index' => "# Example\n\n## Requirements\n\n### PHP\n\nPHP 8.4.\n"]);

    // `## Requirements` straight into a sub-heading is a real section, and a
    // reader searching for it should land on it.
    $result = search('requirements')[0];

    expect($result->heading)->toBe('Requirements')
        ->and($result->url)->toEndWith('#requirements');
});

it('does not repeat the page title as its own section heading', function (): void {
    publishDocumentation(['index' => "# Theme composition\n\nThe contract every package follows.\n"]);

    $result = search('contract every package')[0];

    // Otherwise the breadcrumb reads "Theme composition › Theme composition".
    expect($result->pageTitle)->toBe('Theme composition')
        ->and($result->heading)->toBeNull();
});

it('does not let one page fill the results', function (): void {
    $sections = collect(range(1, 8))
        ->map(fn (int $n): string => "## Section {$n}\n\nThe theming contract, again.\n")
        ->implode("\n");

    publishDocumentation([
        'theming' => "# Theming\n\n{$sections}",
        'elsewhere' => "# Elsewhere\n\nThe theming contract is mentioned here too.\n",
    ]);

    $results = search('theming contract');

    // The strongest hit still leads; what is held back is one document's
    // fourth section and beyond, so the rest of the ecosystem stays visible.
    expect(array_slice(array_map(fn ($r): string => $r->pageTitle, $results), 0, 3))
        ->toBe(['Theming', 'Theming', 'Theming'])
        ->and(array_map(fn ($r): string => $r->pageTitle, $results))
        ->toContain('Elsewhere');
});
