<?php

declare(strict_types=1);

namespace App\Documentation\Support;

use App\Models\ProjectVersion;

/**
 * What the router made of a path after the project slug.
 *
 * `canonical` is false when the reader arrived without a version segment and
 * the project has one to give. The versioned URL is always the canonical one,
 * so that case is a redirect rather than the same content rendered at two
 * addresses.
 */
final readonly class VersionResolution
{
    public function __construct(
        public ProjectVersion $version,
        public string $page,
        public bool $canonical,
    ) {}
}
