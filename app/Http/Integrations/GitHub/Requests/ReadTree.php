<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Every path in a commit, in one request.
 *
 * The tree rather than the zipball. GitHub builds archives with `git archive`,
 * which honours `export-ignore` — and every package marks `/docs` that way so
 * Composer installs stay small. An archive of a documented repository therefore
 * contains everything except its documentation, and the sync quietly falls back
 * to the README. The tree lists what the commit holds, attributes or not.
 */
class ReadTree extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $repository,
        private readonly string $commit,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/repos/{$this->repository}/git/trees/".rawurlencode($this->commit);
    }

    /** @return array<string, string> */
    protected function defaultQuery(): array
    {
        return ['recursive' => '1'];
    }
}
