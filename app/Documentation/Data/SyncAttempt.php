<?php

declare(strict_types=1);

namespace App\Documentation\Data;

use App\Models\ProjectVersion;
use Throwable;

/**
 * What happened when one version was synchronized.
 *
 * A failure is a value here rather than an exception escaping, because one
 * version failing must not stop the others: a single broken manifest holding up
 * every project's documentation is the outcome this shape exists to prevent.
 */
final readonly class SyncAttempt
{
    /** @param list<ValidationIssue> $issues */
    public function __construct(
        public ProjectVersion $version,
        public ?SyncResult $result = null,
        public ?Throwable $failure = null,
        public array $issues = [],
    ) {}

    public function failed(): bool
    {
        return $this->failure instanceof Throwable;
    }

    public function published(): bool
    {
        return $this->result?->wasPublished() === true;
    }

    /** `example 1.x` — how a version is named wherever one is reported. */
    public function label(): string
    {
        return "{$this->version->project->slug} {$this->version->version}";
    }

    /**
     * One line describing the outcome.
     *
     * Lives here so the console row and the panel notification cannot drift
     * into describing the same synchronization differently.
     */
    public function summary(): string
    {
        if ($this->failure instanceof Throwable) {
            return $this->failure->getMessage();
        }

        if (! $this->result instanceof SyncResult) {
            return 'nothing to report';
        }

        if (! $this->result->wasPublished()) {
            return 'unchanged';
        }

        return sprintf(
            '%s · %d page(s)%s · %s',
            $this->result->documentationType->value,
            $this->result->pagesIndexed,
            $this->result->pagesRemoved > 0 ? ", {$this->result->pagesRemoved} removed" : '',
            mb_substr($this->result->commit, 0, 12),
        );
    }
}
