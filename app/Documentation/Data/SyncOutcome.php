<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * What one synchronization did.
 *
 * `Unchanged` is the common case and is not a failure: the ref still resolves
 * to the commit already published, so there is nothing to do.
 */
enum SyncOutcome: string
{
    case Unchanged = 'unchanged';
    case Published = 'published';
}
