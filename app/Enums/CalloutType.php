<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A documentation callout, named the way GitHub names it.
 *
 * GitHub's alert syntax is the supported one: it renders on GitHub, where these pages
 * are read during review, it degrades to an ordinary blockquote everywhere
 * else, and it needs no author retraining. A page that only renders correctly
 * on the hub has stopped being reviewable in a pull request.
 *
 * `Important` keeps its own treatment rather than folding into `Warning`. An
 * `[!IMPORTANT]` usually marks a load-bearing fact — a version requirement, a
 * config-merge trap — and rendering those as hazards would overstate them.
 */
enum CalloutType: string
{
    case Note = 'note';
    case Tip = 'tip';
    case Important = 'important';
    case Warning = 'warning';
    case Danger = 'danger';

    /** The marker as it is written, e.g. `NOTE` from `> [!NOTE]`. */
    public static function fromAlert(string $alert): ?self
    {
        return match (mb_strtoupper(mb_trim($alert))) {
            'NOTE' => self::Note,
            'TIP' => self::Tip,
            'IMPORTANT' => self::Important,
            'WARNING' => self::Warning,
            // GitHub's CAUTION is the strongest of the five: negative
            // consequences, not merely something to watch out for.
            'CAUTION' => self::Danger,
            default => null,
        };
    }

    /** The heading shown above the callout body, as GitHub renders it. */
    public function label(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Tip => 'Tip',
            self::Important => 'Important',
            self::Warning => 'Warning',
            self::Danger => 'Caution',
        };
    }
}
