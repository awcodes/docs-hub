<?php

declare(strict_types=1);

namespace App\Documentation\Data;

use App\Models\Project;
use App\Models\ProjectVersion;

/**
 * Which page is being rendered, and on behalf of what.
 *
 * Rendering Markdown needs none of this; resolving a relative link needs all of
 * it. `configuration.md` means a different URL on 1.x than on 2.x, and
 * that is the entire reason documentation can be cherry-picked between
 * supported branches without rewriting a link.
 */
final readonly class PageContext
{
    /**
     * @param  string  $page  the current page's reference, e.g. `architecture/boundaries`
     * @param  array<string, string>  $resolved  reference => resolved reference, for pages
     *                                           whose front matter overrides the final segment
     */
    public function __construct(
        public Project $project,
        public ProjectVersion $version,
        public string $page,
        public array $resolved = [],
    ) {}

    /** The directory the current page sits in, relative to the documentation root. */
    public function directory(): string
    {
        $page = mb_trim($this->page, '/');

        return str_contains($page, '/')
            ? mb_substr($page, 0, (int) mb_strrpos($page, '/'))
            : '';
    }

    /**
     * What a page reference is actually served as.
     *
     * A `slug` in front matter replaces the final segment only,
     * so a link written at the file it points to still lands on the page.
     */
    public function resolve(string $reference): string
    {
        return $this->resolved[$reference] ?? $reference;
    }
}
