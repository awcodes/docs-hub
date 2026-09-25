<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Turn a branch, tag or SHA into the commit it names.
 *
 * `/commits/{ref}` rather than `/git/ref/heads/{branch}`, because it answers
 * for all three without the caller having to know which it was handed — and a
 * version may be pinned to a tag as easily as to a branch.
 */
class ResolveRef extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $repository,
        private readonly string $ref,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/repos/{$this->repository}/commits/".rawurlencode($this->ref);
    }
}
