<?php

declare(strict_types=1);

namespace App\Documentation\Exceptions;

use App\Models\Project;
use App\Models\ProjectVersion;
use RuntimeException;
use Throwable;

/**
 * A source could not hand over a version's documentation.
 *
 * Always recoverable at the caller: a failed synchronization keeps serving the
 * last valid snapshot and reports itself, rather than taking the version down.
 */
final class DocumentationSourceFailed extends RuntimeException
{
    public static function localPathNotConfigured(Project $project): self
    {
        return new self(
            "No local source path is configured for `{$project->slug}`. "
            .'Add one under `documentation.local_sources`.',
        );
    }

    public static function localPathMissing(Project $project, string $path): self
    {
        return new self(
            "The local source for `{$project->slug}` points at `{$path}`, which is not a directory.",
        );
    }

    public static function refUnresolvable(
        Project $project,
        ProjectVersion $version,
        ?Throwable $previous = null,
        bool $mayBeUnreadable = false,
    ): self {
        // Not a reason to unregister the version. That stays an administrative
        // decision: a ref that stops resolving means the last good snapshot
        // keeps serving and somebody is told.
        //
        // `$mayBeUnreadable` is the caller saying the remote answered the way
        // it answers a repository it will not show. That reads like a missing
        // ref when it is usually a private or renamed repository.
        $hint = $mayBeUnreadable
            ? ' GitHub answers exactly this way for a repository that is private or has been renamed: '
                ."check that `{$project->repository}` is public and spelled as GitHub spells it."
            : '';

        return new self(
            "`{$project->repository}` has no ref `{$version->git_ref}`, or it could not be read."
            .$hint
            .($previous instanceof Throwable ? ' '.$previous->getMessage() : ''),
        );
    }

    public static function archiveUnavailable(
        Project $project,
        ProjectVersion $version,
        ?Throwable $previous = null,
    ): self {
        return new self(
            "The archive for `{$project->repository}` at `{$version->git_ref}` could not be downloaded."
            .($previous instanceof Throwable ? ' '.$previous->getMessage() : ''),
        );
    }

    public static function archiveUnreadable(string $path): self
    {
        return new self("The archive at `{$path}` is not a readable zip.");
    }

    public static function destinationUnwritable(string $destination): self
    {
        return new self("Could not write documentation into `{$destination}`.");
    }
}
