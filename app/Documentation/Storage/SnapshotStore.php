<?php

declare(strict_types=1);

namespace App\Documentation\Storage;

use App\Documentation\Data\SnapshotFile;
use App\Documentation\Exceptions\SnapshotFailed;
use App\Enums\DocumentationType;
use App\Models\ProjectVersion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

/**
 * Where synchronized documentation lives, addressed by commit.
 *
 * ```text
 * {project}/{version}/{commit}/docs/index.md
 * ```
 *
 * A snapshot is written whole and then left alone; publication is a pointer
 * update on `project_versions.active_snapshot` and never a rename. Renaming a
 * directory over the live one is atomic on a local filesystem and has no
 * equivalent on object storage, where directories are a naming convention and a
 * rename is a copy followed by a delete — and the filesystem abstraction exists
 * precisely so that swap can happen later.
 *
 * Addressing by commit is what makes rollback a pointer update, keeps the
 * published commit observable on disk, leaves a failed sync as an unreferenced
 * directory rather than a half-written live one, and gives render caches keys
 * that are naturally distinct across commits.
 */
final readonly class SnapshotStore
{
    public function disk(): Filesystem
    {
        /** @var string $disk */
        $disk = config('documentation.snapshots.disk', 'documentation');

        return Storage::disk($disk);
    }

    /**
     * Copy a retrieved tree into the snapshot for this commit.
     *
     * Ingested from a local directory rather than written in place because a
     * `DocumentationSource` hands over local paths and the disk may not be
     * local. The extra copy is the price of that seam, and it is what keeps a
     * half-finished sync from ever being addressable as a snapshot.
     *
     * @throws SnapshotFailed
     */
    public function write(ProjectVersion $version, string $commit, string $source): string
    {
        $source = mb_rtrim($source, '/');

        if (! is_dir($source)) {
            throw SnapshotFailed::sourceMissing($source);
        }

        $path = $this->path($version, $commit);
        $disk = $this->disk();

        $files = Finder::create()->files()->in($source)->ignoreDotFiles(true)->sortByName();

        $written = 0;

        foreach ($files as $file) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

            $stream = fopen($file->getPathname(), 'rb');

            if ($stream === false) {
                throw SnapshotFailed::unreadable($file->getPathname());
            }

            // Streamed rather than read whole: an asset in a documentation set
            // is a screenshot or a font, and there is no reason for its size to
            // be bounded by memory.
            $ok = $disk->put("{$path}/{$relative}", $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($ok === false) {
                throw SnapshotFailed::unwritable("{$path}/{$relative}");
            }

            $written++;
        }

        if ($written === 0) {
            throw SnapshotFailed::sourceEmpty($source);
        }

        return $path;
    }

    /**
     * What this snapshot turned out to contain.
     *
     * Answered from the snapshot rather than carried in from synchronization,
     * so that the stored `documentation_type` always describes what is actually
     * on disk. `docs/` takes precedence over the README.
     */
    public function documentationType(ProjectVersion $version, string $commit): DocumentationType
    {
        $path = $this->path($version, $commit);
        $disk = $this->disk();
        $docsPath = mb_trim($version->project->docs_path, '/');

        if ($disk->exists("{$path}/{$docsPath}/docs.yml")) {
            return DocumentationType::Structured;
        }

        if ($disk->exists("{$path}/README.md")) {
            return DocumentationType::Readme;
        }

        return DocumentationType::None;
    }

    public function exists(ProjectVersion $version, string $commit): bool
    {
        return $this->disk()->directoryExists($this->path($version, $commit));
    }

    /**
     * Read one file out of a snapshot.
     *
     * Null rather than an exception for a missing file: a page that is not
     * there is a documentation-aware 404, which is the caller's decision to
     * make.
     */
    public function read(ProjectVersion $version, string $commit, string $path): ?string
    {
        $path = $this->path($version, $commit).'/'.$this->safeRelativePath($path);

        return $this->disk()->exists($path) ? $this->disk()->get($path) : null;
    }

    /**
     * Read one file out of whatever this version currently publishes.
     *
     * The pointer is read from the column, never inferred from what happens to
     * be on disk.
     */
    public function readPublished(ProjectVersion $version, string $path): ?string
    {
        return $version->active_snapshot === null
            ? null
            : $this->read($version, $version->active_snapshot, $path);
    }

    /**
     * Open a file from the published snapshot for streaming.
     *
     * The media type is the caller's, from its own allow-list, because this is
     * the one class allowed to touch the disk and the decision about what may
     * be served is not a storage decision.
     *
     * Null when the version publishes nothing, or the file is not in the
     * snapshot it publishes.
     */
    public function openPublished(ProjectVersion $version, string $path, string $mimeType): ?SnapshotFile
    {
        if ($version->active_snapshot === null) {
            return null;
        }

        $full = $this->path($version, $version->active_snapshot)
            .'/'.$this->safeRelativePath($path);

        $disk = $this->disk();

        if (! $disk->exists($full)) {
            return null;
        }

        $stream = $disk->readStream($full);

        if (! is_resource($stream)) {
            return null;
        }

        return new SnapshotFile($stream, $disk->size($full), $mimeType);
    }

    /**
     * Every snapshot retained for this version.
     *
     * @return list<string>
     */
    public function snapshots(ProjectVersion $version): array
    {
        $prefix = $this->versionPath($version);

        return array_values(array_map(
            basename(...),
            $this->disk()->directories($prefix),
        ));
    }

    public function delete(ProjectVersion $version, string $commit): void
    {
        $this->disk()->deleteDirectory($this->path($version, $commit));
    }

    /**
     * Drop the oldest superseded snapshots, keeping `$keep` and the retained
     * few behind it.
     *
     * Ordered by modification time rather than by name, because a commit hash
     * sorts alphabetically and that has nothing to do with age.
     *
     * @return list<string> the commits removed
     */
    public function prune(ProjectVersion $version, string $keep, int $retain): array
    {
        $retain = max(1, $retain);
        $disk = $this->disk();

        $superseded = array_values(array_filter(
            $this->snapshots($version),
            static fn (string $commit): bool => $commit !== $keep,
        ));

        usort(
            $superseded,
            fn (string $a, string $b): int => $disk->lastModified($this->path($version, $b).'/')
                <=> $disk->lastModified($this->path($version, $a).'/'),
        );

        $removed = array_slice($superseded, $retain - 1);

        foreach ($removed as $commit) {
            $this->delete($version, $commit);
        }

        return $removed;
    }

    /** `{project}/{version}` */
    public function versionPath(ProjectVersion $version): string
    {
        return $this->segment($version->project->slug).'/'.$this->segment($version->version);
    }

    /** `{project}/{version}/{commit}` */
    public function path(ProjectVersion $version, string $commit): string
    {
        return $this->versionPath($version).'/'.$this->segment($commit);
    }

    /**
     * A single path segment, refused if it could mean anything but itself.
     *
     * Slugs and version strings are registry values typed by a person, and a
     * commit is whatever a source decided to call one. None of them has any
     * business containing a separator.
     *
     * @throws SnapshotFailed
     */
    private function segment(string $value): string
    {
        if (in_array($value, ['', '.', '..'], true) || preg_match('#[/\\\\]#', $value) === 1) {
            throw SnapshotFailed::unusableSegment($value);
        }

        return $value;
    }

    /**
     * A relative path inside a snapshot that cannot climb out of it.
     *
     * @throws SnapshotFailed
     */
    private function safeRelativePath(string $path): string
    {
        $path = mb_trim(str_replace('\\', '/', $path), '/');

        foreach (explode('/', $path) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                throw SnapshotFailed::unusablePath($path);
            }
        }

        return $path;
    }
}
