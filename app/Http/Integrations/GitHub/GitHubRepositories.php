<?php

declare(strict_types=1);

namespace App\Http\Integrations\GitHub;

use App\Http\Integrations\GitHub\Requests\DownloadRawFile;
use App\Http\Integrations\GitHub\Requests\ReadTree;
use App\Http\Integrations\GitHub\Requests\ResolveRef;
use Saloon\Exceptions\Request\RequestException;
use Throwable;

/**
 * What synchronization asks GitHub for, without the HTTP client.
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
        private RawContentConnector $raw = new RawContentConnector,
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
     * Every file path in a commit.
     *
     * Blobs only: directories are implied by their contents, a submodule is
     * another repository's business, and a symlink's blob is its target rather
     * than a file worth publishing.
     *
     * GitHub truncates a recursive tree past a size limit and says so rather
     * than failing. Publishing from a partial listing would drop pages without
     * a word, so a truncated tree is a failure here.
     *
     * @return list<string>
     *
     * @throws GitHubRequestFailed
     */
    public function listFiles(string $repository, string $commit): array
    {
        $response = $this->send(fn (): mixed => $this->api->send(new ReadTree($repository, $commit)));

        if (($response['truncated'] ?? false) === true) {
            throw new GitHubRequestFailed("The tree for `{$repository}` at `{$commit}` is too large to list in one request.");
        }

        /** @var list<array{path?: mixed, type?: mixed, mode?: mixed}> $entries */
        $entries = is_array($response['tree'] ?? null) ? $response['tree'] : [];

        $paths = [];

        foreach ($entries as $entry) {
            if (($entry['type'] ?? null) !== 'blob' || ($entry['mode'] ?? null) === '120000') {
                continue;
            }

            if (is_string($entry['path'] ?? null) && $entry['path'] !== '') {
                $paths[] = $entry['path'];
            }
        }

        return $paths;
    }

    /**
     * One file's contents at a commit, as bytes.
     *
     * @throws GitHubRequestFailed
     */
    public function downloadFile(string $repository, string $commit, string $path): string
    {
        try {
            $response = $this->raw->send(new DownloadRawFile($repository, $commit, $path));

            $response->throw();

            return $response->body();
        } catch (RequestException $exception) {
            throw new GitHubRequestFailed(
                "`{$path}` in `{$repository}` at `{$commit}` could not be downloaded.",
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
