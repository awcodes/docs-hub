<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * A navigation entry that points at a page.
 *
 * The reference is relative to the documentation root and carries no `.md`
 * extension — `installation`, `usage/authentication` — because it is the URL
 * the reader will see as much as the file on disk.
 */
final readonly class NavigationPage implements NavigationEntry
{
    public function __construct(public string $reference) {}

    /** Where the page lives inside the documentation root. */
    public function sourcePath(): string
    {
        return $this->reference.'.md';
    }
}
