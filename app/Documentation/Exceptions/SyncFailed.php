<?php

declare(strict_types=1);

namespace App\Documentation\Exceptions;

use App\Documentation\Data\ValidationIssue;
use App\Models\ProjectVersion;
use RuntimeException;

/**
 * A synchronization that could not publish.
 *
 * Always safe. Everything before the pointer update operates on a snapshot that
 * is not serving traffic, so there is nothing to roll back — the previous
 * snapshot is still live and abandoning this one leaves an unreferenced
 * directory.
 */
final class SyncFailed extends RuntimeException
{
    /** @param list<ValidationIssue> $issues */
    public function __construct(string $message, public readonly array $issues = [])
    {
        parent::__construct($message);
    }

    /** @param list<ValidationIssue> $issues */
    public static function documentationInvalid(ProjectVersion $version, array $issues): self
    {
        return new self(
            "`{$version->project->slug}` {$version->version} did not pass validation, so "
            .'the previous snapshot is still being served.',
            $issues,
        );
    }

    public static function nothingToPublish(ProjectVersion $version): self
    {
        return new self(
            "`{$version->project->slug}` {$version->version} has neither a documentation "
            .'directory nor a README.',
        );
    }
}
