<?php

declare(strict_types=1);

namespace App\Documentation\Exceptions;

use RuntimeException;

/**
 * A snapshot could not be written, read or published.
 *
 * Never leaves a version worse off than it was: a snapshot that fails to write
 * is an unreferenced directory, and the pointer still names the last one that
 * worked.
 */
final class SnapshotFailed extends RuntimeException
{
    public static function sourceMissing(string $source): self
    {
        return new self("There is nothing to snapshot at `{$source}`.");
    }

    public static function sourceEmpty(string $source): self
    {
        return new self("`{$source}` holds no files, so there is no snapshot to write.");
    }

    public static function unreadable(string $path): self
    {
        return new self("Could not read `{$path}` while writing a snapshot.");
    }

    public static function unwritable(string $path): self
    {
        return new self("Could not write `{$path}` into snapshot storage.");
    }

    public static function unusableSegment(string $value): self
    {
        return new self("`{$value}` cannot be part of a snapshot path.");
    }

    public static function unusablePath(string $path): self
    {
        return new self("`{$path}` is not a path inside a snapshot.");
    }

    public static function notWritten(string $commit): self
    {
        return new self("Snapshot `{$commit}` cannot be published because it was never written.");
    }
}
