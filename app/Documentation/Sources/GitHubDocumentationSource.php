<?php

declare(strict_types=1);

namespace App\Documentation\Sources;

use App\Documentation\Exceptions\DocumentationSourceFailed;
use App\Http\Integrations\GitHub\GitHubRepositories;
use App\Http\Integrations\GitHub\GitHubRequestFailed;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;

/**
 * Documentation read from GitHub, file by file, at one commit.
 *
 * The only thing this does that the local source does not is turn a ref into
 * bytes — everything above it was built and proven against a checkout on disk,
 * which is why this class is small and late rather than large and first.
 *
 * Not the zipball, though it would be fewer requests. GitHub builds archives
 * with `git archive`, which drops every `export-ignore` path, and packages mark
 * `/docs` that way to keep it out of Composer installs — so the archive of a
 * documented repository is the one place its documentation is missing. Two API
 * requests (resolve the ref, list the tree) and then one raw download per file,
 * which does not count against the API rate limit.
 */
final class GitHubDocumentationSource implements DocumentationSource
{
    /**
     * Refs already resolved by this instance.
     *
     * Synchronization asks for the commit and then asks for the files, and
     * the interface hands the second call a ref rather than a commit — so
     * without this every sync resolves the same ref twice. Scoped to the instance, which lives for one sync, rather than shared
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
            $paths = $this->repositories->listFiles($project->repository, $commit);
        } catch (GitHubRequestFailed $failure) {
            throw DocumentationSourceFailed::treeUnavailable($project, $version, $failure);
        }

        $destination = mb_rtrim($destination, '/');

        foreach ($this->documentation($project, $paths) as $path) {
            try {
                $contents = $this->repositories->downloadFile($project->repository, $commit, $path);
            } catch (GitHubRequestFailed $failure) {
                throw DocumentationSourceFailed::fileUnavailable($project, $version, $path, $failure);
            }

            File::ensureDirectoryExists(dirname("{$destination}/{$path}"));

            if (File::put("{$destination}/{$path}", $contents) === false) {
                throw DocumentationSourceFailed::destinationUnwritable($destination);
            }
        }
    }

    /**
     * The paths worth downloading: the root README and the `docs_path` tree.
     *
     * Anything that would not stay inside the destination once written is
     * dropped. Git will not record `..` as a path segment, but this writes
     * files from a remote answer and should not depend on that.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function documentation(Project $project, array $paths): array
    {
        $docsPath = mb_trim($project->docs_path, '/');

        return array_values(array_filter($paths, function (string $path) use ($docsPath): bool {
            if ($path !== 'README.md' && ! str_starts_with($path, "{$docsPath}/")) {
                return false;
            }

            return array_all(explode('/', $path), fn ($segment): bool => ! in_array($segment, ['', '.', '..'], true));
        }));
    }
}
