<?php

declare(strict_types=1);

namespace App\Documentation\Data;

use App\Enums\DocumentationType;

/**
 * The outcome of synchronizing one project version.
 *
 * Built through the two named constructors rather than directly, because the
 * two outcomes carry different things and a single constructor would have to
 * make half of them nullable for no reason a caller benefits from.
 */
final readonly class SyncResult
{
    private function __construct(
        public SyncOutcome $outcome,
        public string $commit,
        public DocumentationType $documentationType,
        public int $pagesIndexed = 0,
        public int $pagesRemoved = 0,
    ) {}

    /** The ref still resolves to what is already published. */
    public static function unchanged(string $commit, DocumentationType $type): self
    {
        return new self(SyncOutcome::Unchanged, $commit, $type);
    }

    public static function published(
        string $commit,
        DocumentationType $type,
        int $pagesIndexed,
        int $pagesRemoved,
    ): self {
        return new self(SyncOutcome::Published, $commit, $type, $pagesIndexed, $pagesRemoved);
    }

    public function wasPublished(): bool
    {
        return $this->outcome === SyncOutcome::Published;
    }
}
