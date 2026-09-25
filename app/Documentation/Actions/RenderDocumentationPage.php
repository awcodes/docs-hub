<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Documentation\Data\PageContext;
use App\Documentation\Data\RenderedPage;
use App\Documentation\Markdown\MarkdownRenderer;
use App\Documentation\Storage\SnapshotStore;
use App\Models\DocumentationPage;
use App\Models\ProjectVersion;

/**
 * One indexed page, read out of its snapshot and rendered.
 *
 * Null when the file the index promised is not in the snapshot. That is a
 * documentation-aware 404 rather than an exception: the index and the snapshot
 * are updated by the same synchronization, so they disagreeing means a sync was
 * interrupted, and the reader's answer is the same either way.
 */
final readonly class RenderDocumentationPage
{
    public function __construct(
        private SnapshotStore $snapshots = new SnapshotStore,
        private MarkdownRenderer $markdown = new MarkdownRenderer,
    ) {}

    public function handle(DocumentationPage $page): ?RenderedPage
    {
        $version = $page->version;

        $markdown = $this->snapshots->readPublished($version, $page->source_path);

        if ($markdown === null) {
            return null;
        }

        return $this->markdown->render($markdown, new PageContext(
            project: $version->project,
            version: $version,
            page: $this->reference($page),
            resolved: $this->resolutionMap($version),
        ));
    }

    /**
     * Where this page sits relative to the documentation root.
     *
     * `source_path` is snapshot-relative — `docs/usage/authentication.md` —
     * because that is what the store reads. Relative links are written against
     * the documentation root, so the prefix comes off before they resolve.
     */
    private function reference(DocumentationPage $page): string
    {
        return $this->stripDocsPath($page->version, $page->source_path);
    }

    /**
     * Every page in this version, as file reference => URL reference.
     *
     * What a relative link needs in order to honour a front-matter `slug`: the
     * link names the file, and the file may be served under a different final
     * segment. Loaded whole because a version's index is small
     * and a sidebar is about to want it anyway.
     *
     * @return array<string, string>
     */
    private function resolutionMap(ProjectVersion $version): array
    {
        $map = [];

        foreach ($version->pages()->get(['source_path', 'slug']) as $page) {
            $map[$this->stripDocsPath($version, $page->source_path)] = $page->slug;
        }

        return $map;
    }

    private function stripDocsPath(ProjectVersion $version, string $sourcePath): string
    {
        $reference = str_ends_with(mb_strtolower($sourcePath), '.md')
            ? mb_substr($sourcePath, 0, -3)
            : $sourcePath;

        $prefix = mb_trim($version->project->docs_path, '/').'/';

        return str_starts_with($reference, $prefix)
            ? mb_substr($reference, mb_strlen($prefix))
            : $reference;
    }
}
