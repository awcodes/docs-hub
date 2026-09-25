<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * A parsed `docs/docs.yml`.
 *
 * Describes navigation and presentation for one version of one project, and
 * deliberately nothing else: repository URLs, support status and which version
 * is current belong to the hub's registry, not to a file that ships inside a
 * branch.
 */
final readonly class Manifest
{
    /**
     * The manifest schema this application understands.
     *
     * The schema, not the project version. Old branches keep their manifests
     * indefinitely, so a format change years from now must not make a 2026
     * manifest unreadable — which is the whole reason the number exists.
     */
    public const int SCHEMA_VERSION = 1;

    /**
     * @param  list<NavigationEntry>  $navigation
     * @param  array<string, string>  $redirects  old page reference => new page reference
     */
    public function __construct(
        public int $schemaVersion,
        public ?string $title,
        public array $navigation,
        public array $redirects = [],
    ) {}

    /**
     * Every page the navigation names, groups flattened, in reading order.
     *
     * Reading order is what previous/next navigation walks, so the flattening
     * has to preserve it rather than collect into a set.
     *
     * @return list<NavigationPage>
     */
    public function pages(): array
    {
        $pages = [];

        foreach ($this->navigation as $entry) {
            if ($entry instanceof NavigationPage) {
                $pages[] = $entry;

                continue;
            }

            if ($entry instanceof NavigationGroup) {
                foreach ($entry->children as $child) {
                    $pages[] = $child;
                }
            }
        }

        return $pages;
    }

    /** @return list<string> */
    public function pageReferences(): array
    {
        return array_map(
            static fn (NavigationPage $page): string => $page->reference,
            $this->pages(),
        );
    }
}
