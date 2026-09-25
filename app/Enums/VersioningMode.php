<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How many documentation versions a project publishes at once.
 *
 * Held on the project rather than inferred from how many versions exist,
 * because a versioned package on its first day has exactly one version and
 * must still show `1.x` in its URLs. Inferring the mode would
 * silently rewrite a package's URLs the moment a second branch was registered.
 */
enum VersioningMode: string implements HasLabel
{
    /** Several concurrent versions, a branch per major. */
    case Versioned = 'versioned';

    /** Exactly one version, tracking one ref. */
    case Rolling = 'rolling';

    public function getLabel(): string
    {
        return match ($this) {
            self::Versioned => 'Versioned',
            self::Rolling => 'Rolling',
        };
    }

    /**
     * Whether URLs carry a version segment and the version selector renders.
     *
     * A rolling project has one deployed instance and one documentation set,
     * so a selector offering that single version and nothing else is furniture.
     */
    public function showsVersionSegment(): bool
    {
        return $this === self::Versioned;
    }
}
