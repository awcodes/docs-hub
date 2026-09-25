<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The support policy for one documentation version.
 *
 * Deliberately separate from `ProjectVersion::$is_default`, which selects what
 * unversioned URLs resolve to. The two usually agree, but they must be able to
 * diverge: while a beta is being documented, the current version stays the
 * default.
 *
 * Set by people, never derived from branches or Composer metadata — a support
 * policy is a decision, not a fact recoverable from a constraint.
 */
enum VersionStatus: string implements HasLabel
{
    case Current = 'current';
    case Supported = 'supported';
    case Legacy = 'legacy';
    case Beta = 'beta';

    public function getLabel(): string
    {
        return match ($this) {
            self::Current => 'Current',
            self::Supported => 'Supported',
            self::Legacy => 'Legacy',
            self::Beta => 'Beta',
        };
    }

    /**
     * Whether unversioned URLs may resolve to a version with this status.
     *
     * A beta is reachable and searchable, but sending readers who asked for no
     * particular version to documentation for an unreleased one is never right.
     */
    public function canBeDefault(): bool
    {
        return $this !== self::Beta;
    }

    /**
     * Whether readers should be warned they are not on the current version.
     */
    public function showsLegacyNotice(): bool
    {
        return $this === self::Legacy;
    }
}
