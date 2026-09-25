<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;

/**
 * The GitHub API, as one place that knows where it is and who it is.
 *
 * Base URL, credential, API version and user agent live here so that a request
 * class is only ever an operation. Nothing in `App\Documentation` may reach
 * past `DocumentationSource` to touch this — an architecture test says so,
 * because the abstraction is what lets synchronization be tested without GitHub.
 */
class GitHubConnector extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://api.github.com';
    }

    /**
     * Requests left in this hour, as GitHub last reported it.
     *
     * Exposed so that reconciliation can back off deliberately rather than
     * discover exhaustion by failing. Null when the header was
     * absent, which is not the same as zero.
     */
    public function remainingRateLimit(Response $response): ?int
    {
        $remaining = $response->header('X-RateLimit-Remaining');

        return $remaining === null || $remaining === '' ? null : (int) $remaining;
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return [
            // Pinned rather than left to drift. GitHub dates its API and an
            // unpinned client silently follows whatever is current.
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'awcodes-docs-hub',
        ];
    }

    /** @return array<string, mixed> */
    protected function defaultConfig(): array
    {
        return ['timeout' => 30, 'connect_timeout' => 10];
    }

    protected function defaultAuth(): ?TokenAuthenticator
    {
        /** @var string|null $token */
        $token = config('documentation.github.token');

        return $token === null || $token === ''
            ? null
            : new TokenAuthenticator($token, 'Bearer');
    }
}
