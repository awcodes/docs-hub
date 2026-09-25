<?php

declare(strict_types=1);

namespace App\Documentation\Support;

use App\Documentation\Exceptions\RegistryMisconfigured;
use App\Models\Project;
use App\Models\ProjectVersion;

/**
 * Decides whether the second URL segment is a version or the start of a page.
 *
 * They occupy the same position, so the rule has to be explicit:
 * the segment is a version if, and only if, it matches a `version` registered
 * for that project. Otherwise the whole remainder is a page path within the
 * default version.
 *
 * Driven by the registry rather than by a pattern match, so version naming is
 * never constrained to a format. `1.x` and `next` and `2026-09` all work, and
 * none of them has to be guessable by a regular expression.
 */
final readonly class VersionResolver
{
    /** @throws RegistryMisconfigured */
    public function resolve(Project $project, string $path): VersionResolution
    {
        $path = mb_trim($path, '/');

        // A rolling project publishes one documentation set and shows no
        // version segment, so there is nothing here to match against and the
        // whole path is a page.
        if (! $project->versioning_mode->showsVersionSegment()) {
            return new VersionResolution($this->defaultVersion($project), $path, canonical: true);
        }

        $segments = $path === '' ? [] : explode('/', $path);
        $first = $segments[0] ?? null;

        if ($first !== null) {
            $version = $project->versions()->where('version', $first)->first();

            if ($version instanceof ProjectVersion) {
                return new VersionResolution(
                    $version,
                    implode('/', array_slice($segments, 1)),
                    canonical: true,
                );
            }
        }

        return new VersionResolution($this->defaultVersion($project), $path, canonical: false);
    }

    /** @throws RegistryMisconfigured */
    private function defaultVersion(Project $project): ProjectVersion
    {
        $version = $project->defaultVersion()->first();

        if (! $version instanceof ProjectVersion) {
            throw RegistryMisconfigured::noDefaultVersion($project);
        }

        return $version;
    }
}
