<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * One file's bytes, at one commit.
 *
 * Addressed by commit rather than ref, so every file in a snapshot comes from
 * the same point in history even if the branch moves mid-sync.
 */
class DownloadRawFile extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $repository,
        private readonly string $commit,
        private readonly string $path,
    ) {}

    public function resolveEndpoint(): string
    {
        $path = implode('/', array_map(rawurlencode(...), explode('/', $this->path)));

        return "/{$this->repository}/".rawurlencode($this->commit)."/{$path}";
    }
}
