<?php

declare(strict_types=1);

namespace App\Documentation\Exceptions;

use App\Models\Project;
use RuntimeException;

/**
 * The registry cannot answer a question it must be able to answer.
 *
 * Deliberately not a 404. A project with no default version is a configuration
 * mistake a person made and a person can fix, so it is surfaced as one — a 404 would hide it behind something that looks like an
 * ordinary missing page and would be found by nobody.
 */
final class RegistryMisconfigured extends RuntimeException
{
    public static function noDefaultVersion(Project $project): self
    {
        return new self(
            "`{$project->slug}` has no default version, so unversioned URLs cannot resolve. "
            .'Mark exactly one of its versions as the default.',
        );
    }
}
