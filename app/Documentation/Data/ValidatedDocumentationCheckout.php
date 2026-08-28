<?php

declare(strict_types=1);

namespace App\Documentation\Data;

final readonly class ValidatedDocumentationCheckout
{
    /**
     * @param  array<string, DocumentationPageMetadata>  $pages
     */
    public function __construct(
        public DocumentationCheckout $checkout,
        public ?DocumentationManifest $manifest,
        public array $pages,
    ) {}
}
