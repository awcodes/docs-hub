<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * The second hop: the signed `codeload` URL, fetched without a credential.
 *
 * The URL carries its own short-lived authorization and needs no header — which
 * is exactly why the first hop must not follow the redirect itself.
 */
class DownloadSignedArchive extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $url) {}

    public function resolveEndpoint(): string
    {
        return $this->url;
    }
}
