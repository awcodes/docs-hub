<?php

declare(strict_types=1);

use App\Documentation\Exceptions\DocumentationValidationException;
use App\Documentation\Validation\DocumentationManifestParser;

it('parses the versioned navigation and redirects manifest', function (): void {
    $manifest = app(DocumentationManifestParser::class)->parseFile(
        base_path('tests/Fixtures/documentation/structured/docs/docs.yml'),
    );

    expect($manifest->version)->toBe(1)
        ->and($manifest->title)->toBe('Curator')
        ->and($manifest->navigation)->toHaveCount(3)
        ->and($manifest->navigation[2]->label)->toBe('Concepts')
        ->and($manifest->navigation[2]->children[0]->pageReference)->toBe('concepts/media')
        ->and($manifest->redirects)->toBe(['getting-started' => 'install']);
});

it('treats a missing schema version as version one', function (): void {
    $manifest = app(DocumentationManifestParser::class)->parseFile(
        base_path('tests/Fixtures/documentation/missing-navigation/docs/docs.yml'),
    );

    expect($manifest->version)->toBe(1);
});

it('rejects future manifest versions', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'docs-manifest-');
    file_put_contents($path, "version: 2\ntitle: Future\nnavigation: []\n");

    try {
        expect(fn () => app(DocumentationManifestParser::class)->parseFile($path))
            ->toThrow(DocumentationValidationException::class);
    } finally {
        unlink($path);
    }
});
