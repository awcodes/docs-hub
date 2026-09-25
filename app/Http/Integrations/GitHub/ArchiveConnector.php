<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub;

use Saloon\Http\Connector;

/**
 * The second hop of an archive download, deliberately unauthenticated.
 *
 * GitHub answers an archive request with a redirect to `codeload.github.com`,
 * and the signed URL it points at carries its own short-lived authorization in
 * the query string. Sending the API credential there as well would be sending a
 * long-lived token to a host that did not ask for it.
 *
 * A separate connector rather than a flag on the first, so that the credential
 * is absent by construction and cannot be reintroduced by a default header
 * somebody adds later.
 */
class ArchiveConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return '';
    }

    /** @return array<string, mixed> */
    protected function defaultConfig(): array
    {
        return ['timeout' => 120, 'connect_timeout' => 10];
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['User-Agent' => 'awcodes-docs-hub'];
    }
}
