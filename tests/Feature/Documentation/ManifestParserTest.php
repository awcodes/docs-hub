<?php

declare(strict_types=1);

use App\Documentation\Data\Manifest;
use App\Documentation\Data\NavigationGroup;
use App\Documentation\Data\NavigationPage;
use App\Documentation\Exceptions\InvalidManifest;
use App\Documentation\Navigation\ManifestParser;

/*
| Example's own `docs.yml` is the reference implementation, so the first test
| is that the real file parses. The rest are the ways a manifest can be wrong.
*/

function parseManifest(string $yaml): Manifest
{
    return (new ManifestParser)->parse($yaml);
}

it('parses a real package manifest', function (): void {
    $manifest = parseManifest(
        (string) file_get_contents(base_path('tests/fixtures/example/docs/docs.yml')),
    );

    expect($manifest->schemaVersion)->toBe(1)
        ->and($manifest->title)->toBe('Example Package')
        ->and($manifest->redirects)->toBeEmpty()
        ->and($manifest->pageReferences())->toContain(
            'index',
            'installation',
            'usage/authentication',
            'architecture/theme-composition',
        );
});

it('flattens groups into reading order', function (): void {
    $manifest = parseManifest(<<<'YAML'
        navigation:
          - index
          - label: Usage
            children:
              - usage/one
              - usage/two
          - theming
        YAML);

    expect($manifest->pageReferences())->toBe([
        'index',
        'usage/one',
        'usage/two',
        'theming',
    ])
        ->and($manifest->navigation[1])->toBeInstanceOf(NavigationGroup::class)
        ->and($manifest->navigation[0])->toBeInstanceOf(NavigationPage::class);
});

it('treats a manifest with no version key as schema version 1', function (): void {
    expect(parseManifest("navigation:\n  - index")->schemaVersion)->toBe(1);
});

it('refuses a schema version it does not understand', function (): void {
    parseManifest("version: 2\nnavigation:\n  - index");
})->throws(InvalidManifest::class, 'not supported');

it('refuses a manifest that is not valid YAML', function (): void {
    parseManifest('navigation: "unterminated');
})->throws(InvalidManifest::class, 'not valid YAML');

it('refuses a manifest that is a list rather than a mapping', function (): void {
    parseManifest("- index\n- installation");
})->throws(InvalidManifest::class, 'must be a YAML mapping');

it('refuses a manifest with no navigation', function (): void {
    parseManifest('title: Example Package');
})->throws(InvalidManifest::class, 'no `navigation` list');

it('refuses navigation that is not a list', function (): void {
    parseManifest("navigation:\n  index: true");
})->throws(InvalidManifest::class, 'must be a list');

it('refuses a group with no children', function (): void {
    parseManifest("navigation:\n  - label: Usage");
})->throws(InvalidManifest::class, 'has no `children` list');

it('refuses a group nested inside a group', function (): void {
    parseManifest(<<<'YAML'
        navigation:
          - label: Usage
            children:
              - label: Deeper
                children:
                  - usage/one
        YAML);
})->throws(InvalidManifest::class, 'Groups do not nest');

it('refuses an entry that is neither a page nor a group', function (): void {
    parseManifest("navigation:\n  - 42");
})->throws(InvalidManifest::class, 'neither a page reference nor a group');

it('refuses a page reference carrying its file extension', function (): void {
    parseManifest("navigation:\n  - installation.md");
})->throws(InvalidManifest::class, 'carries a file extension');

it('reads redirects as a map of page references', function (): void {
    $manifest = parseManifest(<<<'YAML'
        navigation:
          - index
        redirects:
          getting-started: installation
          sso-integration: architecture/sso-integration
        YAML);

    expect($manifest->redirects)->toBe([
        'getting-started' => 'installation',
        'sso-integration' => 'architecture/sso-integration',
    ]);
});

it('refuses redirects written as a list', function (): void {
    parseManifest("navigation:\n  - index\nredirects:\n  - getting-started");
})->throws(InvalidManifest::class, 'must be a mapping');

it('accepts a manifest with no redirects at all', function (string $yaml): void {
    expect(parseManifest($yaml)->redirects)->toBeEmpty();
})->with([
    'absent' => "navigation:\n  - index",
    'empty' => "navigation:\n  - index\nredirects: {}",
    'null' => "navigation:\n  - index\nredirects:",
]);

it('keeps the source path of a page reference derivable', function (): void {
    expect(new NavigationPage('usage/authentication')->sourcePath())
        ->toBe('usage/authentication.md');
});
