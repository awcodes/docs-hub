<?php

declare(strict_types=1);

namespace App\Documentation\Search;

use App\Documentation\Markdown\MarkdownRenderer;
use App\Documentation\Markdown\SectionSplitter;
use App\Models\DocumentationPage;
use App\Models\ProjectVersion;

/**
 * Search over the page index, in the database the application already runs on.
 *
 * The simplest form that works, shipped in the first usable
 * release, because cross-project search is the reason the hub exists rather
 * than several separate documentation sites. A dedicated engine behind Scout is
 * the upgrade path when result quality justifies operating another service —
 * and it must be self-hosted, since this index holds the full text of private
 * documentation.
 *
 * Fills the `DocumentationIndexer` seam, at the point it was defined for: the
 * page has just been parsed and its content is already in memory.
 */
final readonly class DatabaseDocumentationIndexer implements DocumentationIndexer
{
    public function __construct(
        private SectionSplitter $splitter = new SectionSplitter,
        private MarkdownRenderer $markdown = new MarkdownRenderer,
    ) {}

    public function index(DocumentationPage $page, string $content): void
    {
        // Rewritten wholesale rather than diffed. A page's sections are derived
        // data with no identity worth preserving, and reconciling them would be
        // more code than replacing them.
        $page->sections()->delete();

        $sections = $this->splitter->split($content, $this->markdown->render($content));

        foreach ($sections as $section) {
            $page->sections()->create([
                'project_version_id' => $page->project_version_id,
                ...$section,
            ]);
        }
    }

    /** @param list<string> $slugs */
    public function forget(ProjectVersion $version, array $slugs): void
    {
        if ($slugs === []) {
            return;
        }

        // Cascades from the page would cover a deleted page, but pruning runs
        // before that and a section outliving its page is exactly the stale
        // result pruning exists to prevent.
        $version->pages()->whereIn('slug', $slugs)->each(
            static fn (DocumentationPage $page) => $page->sections()->delete(),
        );
    }
}
