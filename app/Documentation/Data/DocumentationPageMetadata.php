<?php

declare(strict_types=1);

namespace App\Documentation\Data;

final readonly class DocumentationPageMetadata
{
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $slug,
    ) {}
}
