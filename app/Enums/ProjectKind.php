<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a registered project is.
 *
 * Overlaps with `Project::$group` today — every package lands in Packages or
 * Tools, every application in Applications — and that is accepted. `kind` is what the project *is*; `group` is where the homepage
 * files it, and the two diverge the first time a group holds both, such as a
 * tool shipped as an application.
 */
enum ProjectKind: string implements HasLabel
{
    case Package = 'package';
    case Application = 'application';

    public function getLabel(): string
    {
        return match ($this) {
            self::Package => 'Package',
            self::Application => 'Application',
        };
    }
}
