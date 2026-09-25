<?php

declare(strict_types=1);

namespace App\Documentation\Navigation;

use App\Documentation\Data\Manifest;
use App\Documentation\Data\NavigationEntry;
use App\Documentation\Data\NavigationGroup;
use App\Documentation\Data\NavigationPage;
use App\Documentation\Exceptions\InvalidManifest;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Turns a `docs/docs.yml` into a `Manifest`, or refuses.
 *
 * Structure only. Whether the pages a manifest names actually exist on disk is
 * the validator's question (`ValidateDocumentation`), because it needs a
 * documentation root and this needs only a string — which is what lets the
 * parser be tested against a literal manifest and reused by anything holding
 * one.
 */
final class ManifestParser
{
    /** @throws InvalidManifest */
    public function parse(string $yaml): Manifest
    {
        try {
            $parsed = Yaml::parse($yaml);
        } catch (ParseException $exception) {
            throw InvalidManifest::unreadableYaml($exception->getMessage());
        }

        if (! is_array($parsed) || array_is_list($parsed)) {
            throw InvalidManifest::notAMapping();
        }

        return new Manifest(
            schemaVersion: $this->schemaVersion($parsed),
            title: $this->title($parsed),
            navigation: $this->navigation($parsed),
            redirects: $this->redirects($parsed),
        );
    }

    /**
     * @param  array<mixed>  $parsed
     *
     * @throws InvalidManifest
     */
    private function schemaVersion(array $parsed): int
    {
        // A manifest written before the key existed is a version 1 manifest.
        $version = $parsed['version'] ?? Manifest::SCHEMA_VERSION;

        // Refused rather than read optimistically: a manifest from a future
        // format may mean something different by the same keys, and guessing
        // would publish a navigation tree its author never wrote.
        if ($version !== Manifest::SCHEMA_VERSION) {
            throw InvalidManifest::unsupportedSchemaVersion($version);
        }

        return $version;
    }

    /** @param array<mixed> $parsed */
    private function title(array $parsed): ?string
    {
        $title = $parsed['title'] ?? null;

        return is_string($title) && $title !== '' ? $title : null;
    }

    /**
     * @param  array<mixed>  $parsed
     * @return list<NavigationEntry>
     *
     * @throws InvalidManifest
     */
    private function navigation(array $parsed): array
    {
        if (! array_key_exists('navigation', $parsed)) {
            throw InvalidManifest::navigationMissing();
        }

        $navigation = $parsed['navigation'];

        if (! is_array($navigation) || ! array_is_list($navigation)) {
            throw InvalidManifest::navigationNotAList();
        }

        $entries = [];

        foreach ($navigation as $position => $entry) {
            $entries[] = $this->entry($entry, $position + 1);
        }

        return $entries;
    }

    /** @throws InvalidManifest */
    private function entry(mixed $entry, int $position): NavigationEntry
    {
        if (is_string($entry)) {
            return $this->page($entry);
        }

        if (! is_array($entry) || ! isset($entry['label']) || ! is_string($entry['label'])) {
            throw InvalidManifest::unrecognizedEntry($position);
        }

        return $this->group($entry['label'], $entry['children'] ?? null);
    }

    /** @throws InvalidManifest */
    private function group(string $label, mixed $children): NavigationGroup
    {
        if (! is_array($children) || ! array_is_list($children) || $children === []) {
            throw InvalidManifest::groupWithoutChildren($label);
        }

        $pages = [];

        foreach ($children as $child) {
            // One level, and the parser says so rather than flattening. A
            // sidebar that silently loses a level is harder to notice than a
            // manifest that was rejected.
            if (! is_string($child)) {
                throw InvalidManifest::nestedGroup($label);
            }

            $pages[] = $this->page($child);
        }

        return new NavigationGroup($label, $pages);
    }

    /** @throws InvalidManifest */
    private function page(string $reference): NavigationPage
    {
        $reference = mb_trim($reference);

        // `installation.md` is the obvious mistake, and silently trimming it
        // would leave the author believing extensions are part of the format.
        if (str_ends_with(mb_strtolower($reference), '.md')) {
            throw InvalidManifest::referenceWithExtension($reference);
        }

        return new NavigationPage(mb_trim($reference, '/'));
    }

    /**
     * @param  array<mixed>  $parsed
     * @return array<string, string>
     *
     * @throws InvalidManifest
     */
    private function redirects(array $parsed): array
    {
        $redirects = $parsed['redirects'] ?? null;

        // Absent, or present and empty. `[]` is indistinguishable from an empty
        // list in PHP, so it has to be answered before the list check below
        // decides a manifest with no redirects is a malformed one.
        if ($redirects === null || $redirects === []) {
            return [];
        }

        if (! is_array($redirects) || array_is_list($redirects)) {
            throw InvalidManifest::redirectsNotAMap();
        }

        $map = [];

        foreach ($redirects as $from => $to) {
            if (! is_string($to)) {
                throw InvalidManifest::redirectNotAString((string) $from);
            }

            $map[mb_trim((string) $from, '/')] = mb_trim($to, '/');
        }

        return $map;
    }
}
