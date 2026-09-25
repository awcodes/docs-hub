<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * Everything wrong with one documentation set, gathered rather than thrown.
 *
 * An author fixing a manifest wants the whole list, not the first line of it,
 * so validation collects and reports once — the exception is a manifest that
 * will not parse, which leaves nothing further to check.
 */
final readonly class ValidationResult
{
    /** @param list<ValidationIssue> $issues */
    public function __construct(public array $issues = []) {}

    public function passed(): bool
    {
        return $this->errors() === [];
    }

    public function failed(): bool
    {
        return ! $this->passed();
    }

    /** @return list<ValidationIssue> */
    public function errors(): array
    {
        return $this->ofSeverity(ValidationSeverity::Error);
    }

    /** @return list<ValidationIssue> */
    public function warnings(): array
    {
        return $this->ofSeverity(ValidationSeverity::Warning);
    }

    /** @return list<ValidationIssue> */
    private function ofSeverity(ValidationSeverity $severity): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (ValidationIssue $issue): bool => $issue->severity === $severity,
        ));
    }
}
