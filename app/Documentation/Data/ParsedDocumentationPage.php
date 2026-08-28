<?php

declare(strict_types=1);

namespace App\Documentation\Data;

final readonly class ParsedDocumentationPage
{
    /**
     * @param  list<array{level: int, title: string, anchor: string}>  $headings
     * @param  list<array{heading_path: list<string>, heading: ?string, anchor: ?string, body: string, sort_order: int}>  $sections
     */
    public function __construct(
        public string $title,
        public array $headings,
        public array $sections,
        public string $contentHash,
    ) {}
}
