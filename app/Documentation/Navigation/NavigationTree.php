<?php

declare(strict_types=1);

namespace App\Documentation\Navigation;

use App\Documentation\Support\DocumentationUrl;
use App\Models\DocumentationPage;
use App\Models\ProjectVersion;

/**
 * The sidebar and the reading order, built from a version's stored manifest.
 *
 * Two things come out of one pass, because they are the same information read
 * two ways: the tree a reader scans, and the sequence a reader moves through.
 * Only the manifest knows either — the page index knows every page and nothing
 * about the order or the groups they belong to.
 *
 * A page indexed but absent from navigation is absent here too, and stays
 * routable and searchable. That is the point: half-written
 * documentation is previewable without appearing finished.
 */
final readonly class NavigationTree
{
    public function __construct(
        private DocumentationUrl $urls = new DocumentationUrl,
    ) {}

    /**
     * The sidebar: pages and one level of labelled groups, in manifest order.
     *
     * @return list<array{type: string, label?: string, title?: string, url?: string, current?: bool, children?: list<array{title: string, url: string, current: bool}>, open?: bool}>
     */
    public function sidebar(ProjectVersion $version, ?DocumentationPage $current = null): array
    {
        $titles = $this->titles($version);
        $tree = [];

        foreach ($version->navigation ?? [] as $entry) {
            if (isset($entry['page'])) {
                $page = $this->item($version, $entry['page'], $titles, $current);

                if ($page !== null) {
                    $tree[] = ['type' => 'page', ...$page];
                }

                continue;
            }

            if (! isset($entry['label'])) {
                continue;
            }

            $children = [];

            foreach ($entry['children'] ?? [] as $slug) {
                $child = $this->item($version, $slug, $titles, $current);

                if ($child !== null) {
                    $children[] = $child;
                }
            }

            if ($children === []) {
                continue;
            }

            $tree[] = [
                'type' => 'group',
                'label' => $entry['label'],
                'children' => $children,
                // Groups start open and the reader may collapse them. A reader
                // who cannot see where they are in the tree has to guess, and
                // a collapsed sidebar on a documentation set this size hides
                // more than it tidies.
                'open' => true,
            ];
        }

        return $tree;
    }

    /**
     * The page before and after this one in reading order.
     *
     * Groups are presentational, so they flatten away: a reader moving forward
     * from the last page of Usage lands on the first page of Architecture, not
     * on a label that has no URL.
     *
     * @return array{previous: ?array{title: string, url: string}, next: ?array{title: string, url: string}}
     */
    public function neighbours(ProjectVersion $version, DocumentationPage $current): array
    {
        $order = $this->readingOrder($version);
        $position = array_search($current->slug, $order, true);

        // A page absent from navigation has no neighbours rather than arbitrary
        // ones — it is not part of the sequence a reader is following.
        if ($position === false) {
            return ['previous' => null, 'next' => null];
        }

        $titles = $this->titles($version);

        return [
            'previous' => $this->neighbour($version, $order[$position - 1] ?? null, $titles),
            'next' => $this->neighbour($version, $order[$position + 1] ?? null, $titles),
        ];
    }

    /**
     * Every navigated page, flattened, in the order the manifest lists them.
     *
     * @return list<string>
     */
    public function readingOrder(ProjectVersion $version): array
    {
        $order = [];

        foreach ($version->navigation ?? [] as $entry) {
            if (isset($entry['page'])) {
                $order[] = $entry['page'];

                continue;
            }

            foreach ($entry['children'] ?? [] as $slug) {
                $order[] = $slug;
            }
        }

        return $order;
    }

    /**
     * @param  array<string, string>  $titles
     * @return array{title: string, url: string, current: bool}|null
     */
    private function item(
        ProjectVersion $version,
        string $slug,
        array $titles,
        ?DocumentationPage $current,
    ): ?array {
        // Navigation is rewritten by every sync and validated before it
        // publishes, so a missing page here means the two disagree. Skipped
        // rather than rendered as a dead link.
        if (! isset($titles[$slug])) {
            return null;
        }

        return [
            'title' => $titles[$slug],
            'url' => $this->urls->page($version->project, $version, $slug),
            'current' => $current?->slug === $slug,
        ];
    }

    /**
     * @param  array<string, string>  $titles
     * @return array{title: string, url: string}|null
     */
    private function neighbour(ProjectVersion $version, ?string $slug, array $titles): ?array
    {
        if ($slug === null || ! isset($titles[$slug])) {
            return null;
        }

        return [
            'title' => $titles[$slug],
            'url' => $this->urls->page($version->project, $version, $slug),
        ];
    }

    /** @return array<string, string> */
    private function titles(ProjectVersion $version): array
    {
        /** @var array<string, string> $titles */
        $titles = $version->pages()->pluck('title', 'slug')->all();

        return $titles;
    }
}
