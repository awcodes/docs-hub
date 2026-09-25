<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub;

use RuntimeException;
use Throwable;

/**
 * Something GitHub would not answer.
 *
 * The integration's own exception rather than Saloon's, so that a caller can
 * handle a failure without importing the HTTP client that produced it. That is
 * what keeps the boundary true: the documentation domain talks to this
 * folder and never to Saloon.
 */
final class GitHubRequestFailed extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
