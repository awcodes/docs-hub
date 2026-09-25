<?php

declare(strict_types=1);

namespace App\Documentation\Search;

use App\Enums\VersionStatus;

/**
 * One hit, carrying everything a result must communicate.
 *
 * The breadcrumb is a property of the index rather than something rebuilt here,
 * which is what indexing at section-level granularity buys.
 */
final readonly class SearchResult
{
    public function __construct(
        public string $projectName,
        public string $version,
        public VersionStatus $status,
        public bool $showsVersion,
        public string $pageTitle,
        public ?string $heading,
        public string $excerpt,
        public string $url,
        public float $score,
    ) {}
}
