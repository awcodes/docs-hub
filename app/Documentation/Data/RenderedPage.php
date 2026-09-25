<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * One Markdown page, rendered.
 *
 * The headings come out with the HTML rather than being recovered from it
 * later, because the parser already knew them. Storing them is
 * what lets a table of contents render without reparsing Markdown on every
 * request.
 */
final readonly class RenderedPage
{
    /** @param list<array{level: int, title: string, anchor: string}> $headings */
    public function __construct(
        public string $html,
        public array $headings = [],
        public ?FrontMatter $frontMatter = null,
    ) {}

    /**
     * The page's own title: its front matter, or failing that its first
     * heading.
     *
     * A page with neither is not an error — it is a fragment, and the caller
     * falls back to its filename.
     */
    public function title(): ?string
    {
        $declared = $this->frontMatter?->title;

        if ($declared !== null) {
            return $declared;
        }

        return $this->headings[0]['title'] ?? null;
    }
}
