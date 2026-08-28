<?php

declare(strict_types=1);

namespace App\Documentation\Validation;

use App\Documentation\Data\DocumentationPageMetadata;
use App\Documentation\Exceptions\DocumentationValidationException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class MarkdownFrontMatterParser
{
    public function parseFile(string $path): DocumentationPageMetadata
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new DocumentationValidationException(["Unable to read Markdown file [{$path}]."]);
        }

        if (preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', $contents, $matches) !== 1) {
            return new DocumentationPageMetadata(null, null, null);
        }

        try {
            $frontMatter = Yaml::parse($matches[1]);
        } catch (ParseException $exception) {
            throw new DocumentationValidationException(["Invalid front matter in [{$path}]: {$exception->getMessage()}"]);
        }

        if (! is_array($frontMatter)) {
            throw new DocumentationValidationException(["Front matter in [{$path}] must be a map."]);
        }

        $unknownFields = array_diff(array_keys($frontMatter), ['title', 'description', 'slug']);

        if ($unknownFields !== []) {
            throw new DocumentationValidationException(["Unsupported front matter field [{$unknownFields[0]}] in [{$path}]."]);
        }

        foreach (['title', 'description', 'slug'] as $field) {
            if (isset($frontMatter[$field]) && ! is_string($frontMatter[$field])) {
                throw new DocumentationValidationException(["Front matter field [{$field}] in [{$path}] must be a string."]);
            }
        }

        return new DocumentationPageMetadata(
            title: $frontMatter['title'] ?? null,
            description: $frontMatter['description'] ?? null,
            slug: $frontMatter['slug'] ?? null,
        );
    }
}
