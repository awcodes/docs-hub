<?php

declare(strict_types=1);

use App\Documentation\Exceptions\DocumentationValidationException;
use App\Documentation\Validation\ValidateDocumentationCheckout;
use App\Enums\DocumentationType;

it('validates structured documentation and keeps unlisted pages', function (): void {
    $validated = app(ValidateDocumentationCheckout::class)->handle(
        base_path('tests/Fixtures/documentation/structured'),
    );

    expect($validated->checkout->type)->toBe(DocumentationType::Structured)
        ->and($validated->manifest?->title)->toBe('Curator')
        ->and($validated->pages)->toHaveKeys(['index', 'installation', 'concepts/media', 'unlisted'])
        ->and($validated->pages['installation']->slug)->toBe('install');
});

it('uses README fallback when structured documentation is absent', function (): void {
    $validated = app(ValidateDocumentationCheckout::class)->handle(
        base_path('tests/Fixtures/documentation/readme'),
    );

    expect($validated->checkout->type)->toBe(DocumentationType::Readme)
        ->and($validated->manifest)->toBeNull()
        ->and($validated->pages)->toHaveKey('');
});

it('resolves a checkout without documentation', function (): void {
    $validated = app(ValidateDocumentationCheckout::class)->handle(
        base_path('tests/Fixtures/documentation/none'),
    );

    expect($validated->checkout->type)->toBe(DocumentationType::None)
        ->and($validated->pages)->toBeEmpty();
});

it('rejects a navigation reference to a missing page', function (): void {
    expect(fn () => app(ValidateDocumentationCheckout::class)->handle(
        base_path('tests/Fixtures/documentation/missing-navigation'),
    ))->toThrow(DocumentationValidationException::class);
});

it('rejects redirect cycles', function (): void {
    expect(fn () => app(ValidateDocumentationCheckout::class)->handle(
        base_path('tests/Fixtures/documentation/redirect-cycle'),
    ))->toThrow(DocumentationValidationException::class);
});

it('rejects route collisions introduced by front matter slugs', function (): void {
    expect(fn () => app(ValidateDocumentationCheckout::class)->handle(
        base_path('tests/Fixtures/documentation/slug-collision'),
    ))->toThrow(DocumentationValidationException::class);
});
