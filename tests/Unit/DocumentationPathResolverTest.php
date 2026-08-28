<?php

declare(strict_types=1);

use App\Documentation\Exceptions\DocumentationValidationException;
use App\Documentation\Support\DocumentationPathResolver;

it('normalizes page references', function (string $input, string $expected): void {
    expect(app(DocumentationPathResolver::class)->pageReference($input))->toBe($expected);
})->with([
    'plain path' => ['concepts/media', 'concepts/media'],
    'markdown extension' => ['concepts/media.md', 'concepts/media'],
    'percent encoded unicode' => ['caf%C3%A9', 'café'],
]);

it('rejects unsafe page references', function (string $reference): void {
    expect(fn (): string => app(DocumentationPathResolver::class)->pageReference($reference))
        ->toThrow(DocumentationValidationException::class);
})->with([
    'empty' => '',
    'absolute' => '/installation',
    'parent traversal' => '../secrets',
    'embedded traversal' => 'concepts/../secrets',
    'backslash' => 'concepts\\media',
    'encoded separator' => 'concepts%2Fmedia',
    'fragment' => 'installation#requirements',
]);

it('replaces only the final route segment with a slug', function (): void {
    $resolver = app(DocumentationPathResolver::class);

    expect($resolver->routePath('concepts/media.md', 'uploads'))->toBe('concepts/uploads')
        ->and(fn (): string => $resolver->routePath('concepts/media.md', 'moved/uploads'))
        ->toThrow(DocumentationValidationException::class);
});
