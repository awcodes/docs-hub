<?php

declare(strict_types=1);

namespace App\Documentation\Support;

use App\Documentation\Exceptions\DocumentationValidationException;
use Normalizer;

final class DocumentationPathResolver
{
    public function pageReference(string $reference): string
    {
        $reference = mb_trim($reference);

        if ($reference === '' || str_contains($reference, '\\') || str_starts_with($reference, '/')) {
            throw new DocumentationValidationException(["Invalid documentation page reference [{$reference}]."]);
        }

        if (preg_match('/%(?:00|2f|5c)/i', $reference) === 1) {
            throw new DocumentationValidationException(["Encoded path separators are not allowed in [{$reference}]."]);
        }

        $reference = rawurldecode($reference);
        $reference = preg_replace('/\.md$/', '', $reference) ?? $reference;
        $segments = explode('/', $reference);

        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true) || preg_match('/[\x00-\x1F\x7F?#]/u', $segment) === 1) {
                throw new DocumentationValidationException(["Invalid documentation page reference [{$reference}]."]);
            }
        }

        $normalized = Normalizer::normalize(implode('/', $segments), Normalizer::FORM_C);

        if ($normalized === false) {
            throw new DocumentationValidationException(["Unable to normalize documentation page reference [{$reference}]."]);
        }

        return $normalized;
    }

    public function sourcePath(string $reference): string
    {
        return $this->pageReference($reference).'.md';
    }

    public function routePath(string $sourceReference, ?string $slug): string
    {
        $sourceReference = $this->pageReference($sourceReference);

        if ($slug === null) {
            return $sourceReference;
        }

        $slug = $this->pageReference($slug);

        if (str_contains($slug, '/')) {
            throw new DocumentationValidationException(["A page slug may only replace the final route segment [{$slug}]."]);
        }

        $directory = str_contains($sourceReference, '/')
            ? str($sourceReference)->beforeLast('/')->append('/')->toString()
            : '';

        return $directory.$slug;
    }

    public function collisionKey(string $path): string
    {
        return mb_strtolower($this->pageReference($path));
    }
}
