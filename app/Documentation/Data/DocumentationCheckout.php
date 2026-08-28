<?php

declare(strict_types=1);

namespace App\Documentation\Data;

use App\Enums\DocumentationType;

final readonly class DocumentationCheckout
{
    public function __construct(
        public DocumentationType $type,
        public string $repositoryRoot,
        public ?string $documentationRoot,
        public ?string $manifestPath,
        public ?string $readmePath,
    ) {}
}
