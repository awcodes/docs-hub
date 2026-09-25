<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * The outcome of synchronizing a set of versions.
 *
 * Counted rather than rolled up to a single pass/fail, because "two published,
 * one unchanged, one failed" is the honest answer and the caller — a console
 * exit code, a notification — decides what to make of it.
 */
final readonly class SyncSummary
{
    /** @param list<SyncAttempt> $attempts */
    public function __construct(public array $attempts = []) {}

    public function total(): int
    {
        return count($this->attempts);
    }

    public function published(): int
    {
        return count(array_filter($this->attempts, fn (SyncAttempt $a): bool => $a->published()));
    }

    public function unchanged(): int
    {
        return count(array_filter(
            $this->attempts,
            fn (SyncAttempt $a): bool => ! $a->failed() && ! $a->published(),
        ));
    }

    public function failed(): int
    {
        return count($this->failures());
    }

    /** @return list<SyncAttempt> */
    public function failures(): array
    {
        return array_values(array_filter($this->attempts, fn (SyncAttempt $a): bool => $a->failed()));
    }

    public function hasFailures(): bool
    {
        return $this->failures() !== [];
    }

    /** `2 published · 1 unchanged · 1 failed`, omitting what did not happen. */
    public function headline(): string
    {
        if ($this->attempts === []) {
            return 'Nothing to synchronize';
        }

        $parts = array_filter([
            $this->published() > 0 ? "{$this->published()} published" : null,
            $this->unchanged() > 0 ? "{$this->unchanged()} unchanged" : null,
            $this->failed() > 0 ? "{$this->failed()} failed" : null,
        ]);

        return implode(' · ', $parts);
    }
}
