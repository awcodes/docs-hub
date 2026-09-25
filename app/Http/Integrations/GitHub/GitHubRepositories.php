<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub;

use App\Http\Integrations\GitHub\Requests\DownloadArchive;
use App\Http\Integrations\GitHub\Requests\DownloadSignedArchive;
use App\Http\Integrations\GitHub\Requests\ResolveRef;
use Saloon\Exceptions\Request\RequestException;
use Throwable;

/**
 * The two things synchronization asks GitHub for, without the HTTP client.
 *
 * Saloon stops here. `App\Documentation` depends on `DocumentationSource`, the
 * GitHub implementation of it depends on this, and an architecture test holds
 * both lines — so replacing the client later is a change inside
 * this folder rather than a change to the sync pipeline.
 */
final readonly class GitHubRepositories
{
    public function __construct(
        private GitHubConnector $api = new GitHubConnector,
        private ArchiveConnector $archives = new ArchiveConnector,
    ) {}

    /**
     * The commit a branch, tag or SHA currently names.
     *
     * @throws GitHubRequestFailed
     */
    public function resolveCommit(string $repository, string $ref): string
    {
        $response = $this->send(fn (): mixed => $this->api->send(new ResolveRef($repository, $ref)));

        $sha = $response['sha'] ?? null;

        if (! is_string($sha) || $sha === '') {
            throw new GitHubRequestFailed("`{$repository}` returned no commit for `{$ref}`.");
        }

        return $sha;
    }

    /**
     * The repository archive for one commit, as bytes.
     *
     * Two hops, and the split is load-bearing. GitHub answers the first with a
     * 302 to `codeload.github.com`, and an HTTP client that follows a redirect
     * across hosts drops the `Authorization` header — by design, and correctly.
     * Against a public repository the redirected request succeeds anyway and
     * the problem stays invisible; against a private one it returns 404, which
     * reads like a missing ref rather than a dropped credential.
     *
     * So the redirect is read rather than followed, and the signed URL — which
     * carries its own short-lived authorization — is fetched by a connector
     * with no credential to send.
     *
     * @throws GitHubRequestFailed
     */
    public function downloadArchive(string $repository, string $commit): string
    {
        try {
            $response = $this->api->send(new DownloadArchive($repository, $commit));

            if ($response->redirect()) {
                $location = $response->header('Location');

                if ($location === null || $location === '') {
                    throw new GitHubRequestFailed(
                        "`{$repository}` redirected its archive for `{$commit}` to nowhere.",
                        $response->status(),
                    );
                }

                $response = $this->archives->send(new DownloadSignedArchive($location));
            }

            $response->throw();

            return $response->body();
        } catch (RequestException $exception) {
            throw new GitHubRequestFailed(
                "The archive for `{$repository}` at `{$commit}` could not be downloaded.",
                $exception->getResponse()->status(),
                $exception,
            );
        }
    }

    /**
     * @param  callable(): mixed  $send
     * @return array<mixed>
     *
     * @throws GitHubRequestFailed
     */
    private function send(callable $send): array
    {
        try {
            /** @var \Saloon\Http\Response $response */
            $response = $send();
            $response->throw();

            /** @var array<mixed> $body */
            $body = $response->json();

            return $body;
        } catch (RequestException $exception) {
            throw new GitHubRequestFailed(
                $exception->getMessage(),
                $exception->getResponse()->status(),
                $exception,
            );
        } catch (Throwable $exception) {
            throw new GitHubRequestFailed($exception->getMessage(), previous: $exception);
        }
    }
}
