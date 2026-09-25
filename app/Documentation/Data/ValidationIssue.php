<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * One thing wrong with a documentation set.
 *
 * Carries the file it concerns wherever there is one, because the audience is
 * an author reading CI output on a pull request, and "which file" is the first
 * thing they need.
 */
final readonly class ValidationIssue
{
    public function __construct(
        public ValidationSeverity $severity,
        public string $message,
        public ?string $file = null,
    ) {}

    public static function error(string $message, ?string $file = null): self
    {
        return new self(ValidationSeverity::Error, $message, $file);
    }

    public static function warning(string $message, ?string $file = null): self
    {
        return new self(ValidationSeverity::Warning, $message, $file);
    }
}
