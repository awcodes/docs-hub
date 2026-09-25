<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * The optional metadata block at the top of a documentation page.
 *
 * Three fields, and more only when a real requirement appears.
 *
 * `slug` is the one that carries weight. Without it a page's public URL is a
 * pure function of its path on disk, so renaming the file silently breaks every
 * inbound link — including links from the project's own older versions, which
 * nobody is going to go back and fix.
 */
final readonly class FrontMatter
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $slug = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->title === null
            && $this->description === null
            && $this->slug === null;
    }
}
