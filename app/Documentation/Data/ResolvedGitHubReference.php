<?php

declare(strict_types=1);

namespace App\Documentation\Data;

final readonly class ResolvedGitHubReference
{
    public function __construct(
        public string $repository,
        public string $reference,
        public string $commitSha,
    ) {}
}
