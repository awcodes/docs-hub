<?php

declare(strict_types=1);

namespace App\Documentation\Sources;

use App\Documentation\Exceptions\DocumentationSourceFailed;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Documentation read straight from a repository checkout on this machine.
 *
 * Built before the GitHub source rather than after it. A package's conforming
 * `docs/` tree is usually already on disk beside the hub, so every layer
 * above the source can be built and seen with no token, no webhook, no rate
 * limit and no archive-redirect trap — which leaves the GitHub source with one
 * thing left to prove: turning a ref into bytes.
 *
 * It is also what a pull request check needs. Two requirements, one adapter.
 *
 * The version's `git_ref` is ignored, and has to be: a checkout is on whatever
 * branch it is on, and this adapter reads the working tree rather than asking
 * git anything. Mounting a project locally therefore mounts one version of it,
 * whichever is checked out. That is a development affordance, not a way to
 * serve two versions of a package from one directory.
 */
final readonly class LocalFilesystemDocumentationSource implements DocumentationSource
{
    /**
     * A digest of the documentation as it currently sits on disk.
     *
     * Deliberately not the checkout's `HEAD`. A local source exists so that a
     * documentation change is previewable *before* it is committed, and a commit
     * SHA does not move when a working tree does — mounting a checkout and then
     * watching edits never appear would defeat the only reason this adapter is
     * here. Hashing the content instead means a sync is a no-op until something
     * actually changes, which is the property snapshots need from the value.
     */
    public function resolveCommit(Project $project, ProjectVersion $version): string
    {
        $root = $this->root($project);

        $digest = hash_init('sha256');

        foreach ($this->files($project, $root) as $relativePath => $absolutePath) {
            // The path is hashed as well as the contents, so that renaming a
            // file registers as a change even when nothing inside it moved.
            hash_update($digest, $relativePath);
            hash_update($digest, (string) hash_file('sha256', $absolutePath));
        }

        return hash_final($digest);
    }

    public function retrieve(Project $project, ProjectVersion $version, string $destination): void
    {
        $root = $this->root($project);
        // A checkout with neither `docs/` nor a README copies nothing, and that
        // is not this class's problem to have an opinion about. `none` is one
        // of three documentation types, and resolving it is
        // the caller's job — so every source answers the question the same way.
        foreach ($this->files($project, $root) as $relativePath => $absolutePath) {
            $target = mb_rtrim($destination, '/')."/{$relativePath}";

            File::ensureDirectoryExists(dirname($target));

            if (! File::copy($absolutePath, $target)) {
                throw DocumentationSourceFailed::destinationUnwritable($destination);
            }
        }
    }

    /**
     * The configured checkout for this project.
     *
     * @throws DocumentationSourceFailed
     */
    private function root(Project $project): string
    {
        /** @var array<string, string> $paths */
        $paths = config('documentation.local_sources', []);

        $path = $paths[$project->slug] ?? null;

        if (! is_string($path) || $path === '') {
            throw DocumentationSourceFailed::localPathNotConfigured($project);
        }

        $path = mb_rtrim($path, '/');

        if (! is_dir($path)) {
            throw DocumentationSourceFailed::localPathMissing($project, $path);
        }

        return $path;
    }

    /**
     * The documentation files of a checkout, keyed by repository-relative path.
     *
     * Only `docs_path` and the root `README.md`. Copying a whole checkout would
     * mean copying `vendor/` and `node_modules/` for the sake of twenty
     * Markdown files, and the caller has no use for any of it: the type
     * resolution it performs asks exactly these two questions.
     *
     * Sorted, because `resolveCommit()` hashes this in order and a digest that
     * depends on filesystem enumeration order is not a digest.
     *
     * @return array<string, string>
     */
    private function files(Project $project, string $root): array
    {
        $files = [];

        $docsPath = mb_trim($project->docs_path, '/');

        if (is_dir("{$root}/{$docsPath}")) {
            $found = Finder::create()
                ->files()
                ->in("{$root}/{$docsPath}")
                ->ignoreDotFiles(true)
                ->sortByName();

            foreach ($found as $file) {
                $relative = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    $file->getRelativePathname(),
                );

                $files["{$docsPath}/{$relative}"] = $file->getPathname();
            }
        }

        // The fallback is version-aware in the same way the rest is: a README
        // is read from the same place as everything else, not from the default
        // branch.
        if (is_file("{$root}/README.md")) {
            $files['README.md'] = "{$root}/README.md";
        }

        return $files;
    }
}
