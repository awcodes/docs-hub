<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class GetGitTag extends Request
{
    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $repository,
        private readonly string $tagSha,
    ) {}

    /**
     * The endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->repository.'/git/tags/'.$this->tagSha;
    }
}
