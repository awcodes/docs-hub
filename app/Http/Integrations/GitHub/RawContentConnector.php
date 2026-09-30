<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub;

use Saloon\Http\Connector;

/**
 * File contents at a commit, from `raw.githubusercontent.com`.
 *
 * Not the API, and deliberately unauthenticated. Raw downloads do not count
 * against the API rate limit, which matters because a sync fetches one file per
 * request: sixty anonymous API requests an hour would not cover one pass over
 * the registry. Every documented repository is public, so there is no
 * credential this host needs.
 *
 * A separate connector rather than a flag on the API one, so that the token is
 * absent by construction and cannot be reintroduced by a default header
 * somebody adds later.
 */
class RawContentConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://raw.githubusercontent.com';
    }

    /** @return array<string, mixed> */
    protected function defaultConfig(): array
    {
        return ['timeout' => 30, 'connect_timeout' => 10];
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['User-Agent' => 'awcodes-docs-hub'];
    }
}
