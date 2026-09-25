<?php

declare(strict_types=1);

namespace App\Documentation\Support;

use App\Models\Project;
use App\Models\ProjectVersion;

/**
 * The canonical URL for a page or an asset.
 *
 * One class so that link rewriting and routing cannot drift apart. A rewriter
 * that builds `/example/1.x/installation` while the router expects something
 * else produces a documentation set full of working-looking 404s.
 */
final readonly class DocumentationUrl
{
    /** Where documentation assets live inside a documentation root. */
    public const string ASSET_ROOT = 'assets';

    /**
     * `/{project}/{version}/{page}`, or `/{project}/{page}` when rolling.
     *
     * A rolling project has one deployed instance and one documentation set,
     * so there are no registered public versions for a segment to match
     * against and none is emitted.
     *
     * `index` is the version root rather than a page beneath it: the project
     * landing page simply renders the version's `index.md`.
     */
    public function page(Project $project, ProjectVersion $version, string $reference = ''): string
    {
        $segments = [$project->slug];

        if ($project->versioning_mode->showsVersionSegment()) {
            $segments[] = $version->version;
        }

        $reference = mb_trim($reference, '/');

        if ($reference !== '' && $reference !== 'index') {
            $segments[] = $reference;
        }

        return '/'.implode('/', $segments);
    }

    /**
     * `/assets/{project}/{version}/{path}`.
     *
     * Namespaced by project *and* version so that two versions of one project
     * can ship different screenshots under the same relative source path.
     *
     * The version segment stays even for a rolling project. Reading URLs drop
     * it because a version selector offering one version is
     * furniture; an asset URL is plumbing nobody reads, and a uniform shape is
     * worth more here than a shorter one.
     */
    public function asset(Project $project, ProjectVersion $version, string $path): string
    {
        return '/assets/'.implode('/', [
            $project->slug,
            $version->version,
            mb_trim($path, '/'),
        ]);
    }
}
