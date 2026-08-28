<?php

declare(strict_types=1);

namespace App\Documentation\Validation;

use App\Documentation\Data\DocumentationManifest;
use App\Documentation\Data\DocumentationNavigationItem;
use App\Documentation\Exceptions\DocumentationValidationException;
use App\Documentation\Support\DocumentationPathResolver;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class DocumentationManifestParser
{
    public function __construct(private DocumentationPathResolver $pathResolver) {}

    public function parseFile(string $path): DocumentationManifest
    {
        if (! is_file($path)) {
            throw new DocumentationValidationException(["Documentation manifest [{$path}] does not exist."]);
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $exception) {
            throw new DocumentationValidationException(["Invalid documentation manifest: {$exception->getMessage()}"]);
        }

        if (! is_array($data)) {
            throw new DocumentationValidationException(['The documentation manifest must contain a map.']);
        }

        $unknownFields = array_diff(array_keys($data), ['version', 'title', 'navigation', 'redirects']);

        if ($unknownFields !== []) {
            throw new DocumentationValidationException(["Unsupported manifest field [{$unknownFields[0]}]."]);
        }

        $version = $data['version'] ?? 1;

        if (! is_int($version) || $version !== 1) {
            throw new DocumentationValidationException(["Unsupported documentation manifest schema version [{$version}]."]);
        }

        if (isset($data['title']) && ! is_string($data['title'])) {
            throw new DocumentationValidationException(['Manifest title must be a string.']);
        }

        if (! isset($data['navigation']) || ! is_array($data['navigation']) || ! array_is_list($data['navigation'])) {
            throw new DocumentationValidationException(['Manifest navigation must be a list.']);
        }

        $navigation = array_map($this->parseNavigationItem(...), $data['navigation']);
        $redirects = $this->parseRedirects($data['redirects'] ?? []);

        return new DocumentationManifest($version, $data['title'] ?? null, $navigation, $redirects);
    }

    private function parseNavigationItem(mixed $item): DocumentationNavigationItem
    {
        if (is_string($item)) {
            return new DocumentationNavigationItem($this->pathResolver->pageReference($item), null);
        }

        if (! is_array($item) || array_diff(array_keys($item), ['label', 'children']) !== [] || ! array_key_exists('label', $item) || ! array_key_exists('children', $item)) {
            throw new DocumentationValidationException(['Each navigation item must be a page reference or a label with children.']);
        }

        if (! is_string($item['label']) || mb_trim($item['label']) === '' || ! is_array($item['children']) || ! array_is_list($item['children']) || $item['children'] === []) {
            throw new DocumentationValidationException(['Navigation groups require a non-empty label and page-reference children.']);
        }

        $children = [];

        foreach ($item['children'] as $child) {
            if (! is_string($child)) {
                throw new DocumentationValidationException(['Navigation groups may not be nested.']);
            }

            $children[] = new DocumentationNavigationItem($this->pathResolver->pageReference($child), null);
        }

        return new DocumentationNavigationItem(null, mb_trim($item['label']), $children);
    }

    /** @return array<string, string> */
    private function parseRedirects(mixed $redirects): array
    {
        if (! is_array($redirects) || array_is_list($redirects) && $redirects !== []) {
            throw new DocumentationValidationException(['Manifest redirects must be a map.']);
        }

        $parsed = [];

        foreach ($redirects as $source => $destination) {
            if (! is_string($source) || ! is_string($destination)) {
                throw new DocumentationValidationException(['Redirect sources and destinations must be page references.']);
            }

            $normalizedSource = $this->pathResolver->pageReference($source);

            if (array_key_exists($normalizedSource, $parsed)) {
                throw new DocumentationValidationException(["Duplicate redirect source [{$normalizedSource}]."]);
            }

            $parsed[$normalizedSource] = $this->pathResolver->pageReference($destination);
        }

        return $parsed;
    }
}
