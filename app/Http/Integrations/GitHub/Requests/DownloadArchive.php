<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Ask for a repository archive, and do not follow where it points.
 *
 * One request returns the whole repository; the alternative is one request per
 * file through the Contents API, which for a modest `docs/` tree is twenty
 * against two.
 *
 * Redirects are refused on purpose. GitHub answers with a 302 to
 * `codeload.github.com`, and an HTTP client following that across hosts drops
 * the `Authorization` header — by design, and correctly. Against a public
 * repository the redirected request succeeds anyway and the problem stays
 * invisible; against a private one it returns 404, which reads like a missing
 * ref rather than a dropped credential.
 */
class DownloadArchive extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $repository,
        private readonly string $ref,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/repos/{$this->repository}/zipball/".rawurlencode($this->ref);
    }

    /** @return array<string, mixed> */
    protected function defaultConfig(): array
    {
        return ['allow_redirects' => false];
    }
}
