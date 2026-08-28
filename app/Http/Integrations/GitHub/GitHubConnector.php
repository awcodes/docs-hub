<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

final class GitHubConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected float $connectTimeout = 5;

    protected float $requestTimeout = 30;

    /**
     * The Base URL of the API
     */
    public function resolveBaseUrl(): string
    {
        return (string) config('services.github.url', 'https://api.github.com');
    }

    /**
     * Default headers for every request
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => (string) config('app.name'),
        ];
    }

    /**
     * Default HTTP client options
     */
    protected function defaultConfig(): array
    {
        return [];
    }

    protected function defaultAuth(): ?Authenticator
    {
        $token = config('services.github.token');

        return is_string($token) && $token !== ''
            ? new TokenAuthenticator($token)
            : null;
    }
}
