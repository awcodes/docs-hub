<?php

declare(strict_types=1);

namespace App\Documentation\Data;

final readonly class DocumentationManifest
{
    /**
     * @param  list<DocumentationNavigationItem>  $navigation
     * @param  array<string, string>  $redirects
     */
    public function __construct(
        public int $version,
        public ?string $title,
        public array $navigation,
        public array $redirects,
    ) {}
}
