<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * An open handle on one file inside a published snapshot.
 *
 * A stream rather than a string: documentation assets are screenshots, diagrams
 * and fonts, and there is no reason for one to be bounded by how much of it
 * fits in memory at once.
 *
 * The media type travels with it and is decided by the caller from an
 * allow-list, never sniffed from the bytes. Sniffing is how a file uploaded as
 * one thing gets served as another.
 */
final readonly class SnapshotFile
{
    /** @param resource $stream */
    public function __construct(
        public mixed $stream,
        public int $size,
        public string $mimeType,
    ) {}
}
