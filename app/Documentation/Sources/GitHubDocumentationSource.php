<?php

declare(strict_types=1);

namespace App\Documentation\Sources;

use App\Documentation\Exceptions\DocumentationSourceFailed;
use App\Http\Integrations\GitHub\GitHubRepositories;
use App\Http\Integrations\GitHub\GitHubRequestFailed;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Documentation read from GitHub, one archive at a time.
 *
 * The only thing this does that the local source does not is turn a ref into
 * bytes — everything above it was built and proven against a checkout on disk
 *, which is why this class is small and late rather than large and
 * first.
 *
 * Two requests per sync: resolve the ref, download the archive for the commit
 * it named. Walking the tree through the Contents API would cost one request
 * per file, which for a modest `docs/` tree is twenty against two.
 */
final class GitHubDocumentationSource implements DocumentationSource
{
    /**
     * Refs already resolved by this instance.
     *
     * Synchronization asks for the commit and then asks for the archive, and
     * the interface hands the second call a ref rather than a commit — so
     * without this a sync costs three requests where two will do. Scoped to the instance, which lives for one sync, rather than shared
     * where it could go stale in a long-running worker.
     *
     * @var array<string, string>
     */
    private array $resolved = [];

    public function __construct(
        private readonly GitHubRepositories $repositories = new GitHubRepositories,
    ) {}

    public function resolveCommit(Project $project, ProjectVersion $version): string
    {
        $key = "{$project->repository}@{$version->git_ref}";

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        try {
            return $this->resolved[$key] = $this->repositories->resolveCommit(
                $project->repository,
                $version->git_ref,
            );
        } catch (GitHubRequestFailed $failure) {
            // Not a reason to unregister the version. A ref that stops
            // resolving leaves the last good snapshot serving and tells
            // somebody; deciding a version is unsupported is an administrative
            // act, not an inference from a failed lookup.
            //
            // GitHub answers a repository the token cannot see exactly as it
            // answers one that does not exist, and telling the two apart is the
            // integration's business rather than the domain's.
            throw DocumentationSourceFailed::refUnresolvable(
                $project,
                $version,
                $failure,
                mayBeUnreadable: $failure->status === 404,
            );
        }
    }

    public function retrieve(Project $project, ProjectVersion $version, string $destination): void
    {
        $commit = $this->resolveCommit($project, $version);

        try {
            $archive = $this->repositories->downloadArchive($project->repository, $commit);
        } catch (GitHubRequestFailed $failure) {
            throw DocumentationSourceFailed::archiveUnavailable($project, $version, $failure);
        }

        // Written out because `ZipArchive` reads files, not strings. Removed
        // however this ends: a failed sync should not leave the machine holding
        // a copy of every repository it could not unpack.
        $path = storage_path('app/docs-archives/'.Str::random(16).'.zip');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $archive);

        try {
            $this->extract($project, $path, $destination);
        } finally {
            File::delete($path);
        }
    }

    /**
     * Unpack only the documentation, discarding the rest of the repository.
     *
     * A zipball is the whole repository under one generated top-level directory
     * — `acme-example-f7c193d/` — so every path is re-rooted before anything
     * is written. Entries are matched against the two prefixes that matter and
     * everything else is dropped unread, which is both what the caller needs
     * and what keeps a `vendor/` out of a snapshot.
     *
     * @throws DocumentationSourceFailed
     */
    private function extract(Project $project, string $archive, string $destination): void
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw DocumentationSourceFailed::archiveUnreadable($archive);
        }

        try {
            $docsPath = mb_trim($project->docs_path, '/');
            $destination = mb_rtrim($destination, '/');

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }

                $relative = $this->reroot($name);

                if ($relative === null) {
                    continue;
                }

                if ($relative !== 'README.md' && ! str_starts_with($relative, "{$docsPath}/")) {
                    continue;
                }

                $contents = $zip->getFromIndex($index);

                if ($contents === false) {
                    throw DocumentationSourceFailed::archiveUnreadable($archive);
                }

                File::ensureDirectoryExists(dirname("{$destination}/{$relative}"));
                File::put("{$destination}/{$relative}", $contents);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * A zip entry's path with GitHub's generated top-level directory removed.
     *
     * Null for anything that would not stay inside the destination once
     * written. A repository cannot put `..` in a path through git, but an
     * archive is just bytes and this writes files.
     */
    private function reroot(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);

        $cut = mb_strpos($name, '/');

        if ($cut === false) {
            return null;
        }

        $relative = mb_substr($name, $cut + 1);

        foreach (explode('/', $relative) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                return null;
            }
        }

        return $relative;
    }
}
