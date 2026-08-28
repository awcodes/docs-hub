<?php

declare(strict_types=1);

namespace App\Documentation\Data;

final readonly class DocumentationNavigationItem
{
    /**
     * @param  list<self>  $children
     */
    public function __construct(
        public ?string $pageReference,
        public ?string $label,
        public array $children = [],
    ) {}
}
