<?php

declare(strict_types=1);

namespace App\Documentation\Sources;

use App\Documentation\Data\ResolvedGitHubReference;
use App\Documentation\Exceptions\GitHubDocumentationSourceException;
use App\Http\Integrations\GitHub\GitHubConnector;
use App\Http\Integrations\GitHub\Requests\DownloadRepositoryArchive;
use App\Http\Integrations\GitHub\Requests\GetGitReference;
use App\Http\Integrations\GitHub\Requests\GetGitTag;
use Saloon\Http\Response;

final readonly class GitHubDocumentationSource
{
    private const int MaximumTagDepth = 5;

    public function __construct(private GitHubConnector $connector) {}

    public function resolve(string $repository, string $reference): ResolvedGitHubReference
    {
        $this->ensureValidRepository($repository);

        if ($reference === '' || str_contains($reference, '..') || str_starts_with($reference, '/')) {
            throw new GitHubDocumentationSourceException("Invalid GitHub reference [{$reference}].");
        }

        $response = $this->connector->send(new GetGitReference($repository, $reference));
        $object = $this->objectFrom($response, $repository, $reference);

        for ($depth = 0; $object['type'] === 'tag' && $depth < self::MaximumTagDepth; $depth++) {
            $response = $this->connector->send(new GetGitTag($repository, $object['sha']));
            $object = $this->objectFrom($response, $repository, $reference);
        }

        if ($object['type'] !== 'commit' || preg_match('/\A[0-9a-f]{40}\z/', $object['sha']) !== 1) {
            throw new GitHubDocumentationSourceException("GitHub reference [{$repository}@{$reference}] did not resolve to a commit.");
        }

        return new ResolvedGitHubReference($repository, $reference, $object['sha']);
    }

    public function download(ResolvedGitHubReference $reference, string $destination): void
    {
        $response = $this->connector->send(new DownloadRepositoryArchive($reference->repository, $reference->commitSha));

        if ($response->failed()) {
            throw new GitHubDocumentationSourceException("GitHub archive download failed for [{$reference->repository}@{$reference->commitSha}] with status [{$response->status()}].");
        }

        $response->saveBodyToFile($destination);
    }

    /** @return array{type: string, sha: string} */
    private function objectFrom(Response $response, string $repository, string $reference): array
    {
        if ($response->failed()) {
            throw new GitHubDocumentationSourceException("GitHub reference lookup failed for [{$repository}@{$reference}] with status [{$response->status()}].");
        }

        $object = $response->json('object');

        if (! is_array($object) || ! is_string($object['type'] ?? null) || ! is_string($object['sha'] ?? null)) {
            throw new GitHubDocumentationSourceException("GitHub returned an invalid reference for [{$repository}@{$reference}].");
        }

        return ['type' => $object['type'], 'sha' => mb_strtolower($object['sha'])];
    }

    private function ensureValidRepository(string $repository): void
    {
        $segments = explode('/', $repository);

        if (count($segments) !== 2
            || array_any($segments, fn (string $segment): bool => in_array($segment, ['', '.', '..'], true))
            || preg_match('/\A[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\z/', $repository) !== 1) {
            throw new GitHubDocumentationSourceException("Invalid GitHub repository [{$repository}].");
        }
    }
}
