<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * A labelled group of pages.
 *
 * Presentational only: a group label is not a page, has no URL and is not
 * routable. Groups do not nest — `children` holds pages, never further groups.
 * That restriction is deliberate rather than incidental, and the
 * parser enforces it rather than quietly flattening.
 */
final readonly class NavigationGroup implements NavigationEntry
{
    /** @param list<NavigationPage> $children */
    public function __construct(
        public string $label,
        public array $children,
    ) {}
}
