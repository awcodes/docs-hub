<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a synchronized version turned out to contain.
 *
 * Resolved during synchronization and stored, so routing, rendering, search and
 * navigation never re-inspect the snapshot filesystem to answer it.
 *
 * The value moves on its own: adding a `docs/` directory promotes a version from
 * `readme` to `structured` on the next successful sync, and removing one falls
 * back again. That is what lets the registry hold the whole ecosystem from day
 * one rather than only its best-documented corner.
 */
enum DocumentationType: string implements HasLabel
{
    /** A `docs/` directory with a `docs.yml` manifest. */
    case Structured = 'structured';

    /** No `docs/`, but a root `README.md` — a single-page documentation set. */
    case Readme = 'readme';

    /** Neither. Registered, synchronized, and with nothing to show. */
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Structured => 'Structured',
            self::Readme => 'README',
            self::None => 'None',
        };
    }

    public function hasPages(): bool
    {
        return $this !== self::None;
    }
}
