<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * Whether an issue blocks publication.
 *
 * The distinction is load-bearing: an error means the snapshot is not published
 * and the previous one keeps serving, which is a strict rule that
 * is only fair because `docs:validate` runs in the repository's own CI. A
 * warning is worth saying and not worth blocking a merge over.
 */
enum ValidationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
