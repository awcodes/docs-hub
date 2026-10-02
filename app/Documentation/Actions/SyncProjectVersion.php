<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Documentation\Data\FrontMatter;
use App\Documentation\Data\Manifest;
use App\Documentation\Data\NavigationGroup;
use App\Documentation\Data\NavigationPage;
use App\Documentation\Data\SyncResult;
use App\Documentation\Exceptions\SyncFailed;
use App\Documentation\Markdown\FrontMatterParser;
use App\Documentation\Markdown\MarkdownRenderer;
use App\Documentation\Navigation\ManifestParser;
use App\Documentation\Search\DatabaseDocumentationIndexer;
use App\Documentation\Search\DocumentationIndexer;
use App\Documentation\Sources\DocumentationSourceResolver;
use App\Documentation\Storage\SnapshotStore;
use App\Enums\DocumentationType;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Synchronize one project version: resolve, retrieve, validate, publish.
 *
 * The order is not arbitrary. Everything up to the pointer update
 * happens on a snapshot nobody is reading, so a sync that fails anywhere before
 * that leaves the previous snapshot serving and an unreferenced directory
 * behind. There is no rollback path because there is nothing to roll back.
 */
final readonly class SyncProjectVersion
{
    public function __construct(
        private DocumentationSourceResolver $sources = new DocumentationSourceResolver,
        private SnapshotStore $snapshots = new SnapshotStore,
        private ValidateDocumentation $validator = new ValidateDocumentation,
        private PublishSnapshot $publisher = new PublishSnapshot,
        private ManifestParser $manifests = new ManifestParser,
        private FrontMatterParser $frontMatter = new FrontMatterParser,
        private MarkdownRenderer $markdown = new MarkdownRenderer,
        private DocumentationIndexer $indexer = new DatabaseDocumentationIndexer,
    ) {}

    /** @throws SyncFailed */
    public function handle(ProjectVersion $version, bool $force = false): SyncResult
    {
        $project = $version->project;
        $source = $this->sources->for($project);

        $commit = $source->resolveCommit($project, $version);

        // Nothing has moved. Re-publishing identical bytes would churn the
        // snapshot directory and the page index for no reader's benefit.
        // The snapshot has to be where the registry now addresses it, though:
        // its path is built from the project slug and version name, so
        // renaming either leaves the published commit stranded under the old
        // path and only a republish puts it back where readers look.
        if ($commit === $version->source_commit && ! $force && $version->isPublished()
            && $this->snapshots->exists($version, $commit)) {
            return SyncResult::unchanged($commit, $version->documentation_type);
        }

        $staging = $this->staging($version, $commit);

        try {
            $source->retrieve($project, $version, $staging);

            $type = $this->documentationType($project->docs_path, $staging);

            if ($type === DocumentationType::None) {
                throw SyncFailed::nothingToPublish($version);
            }

            if ($type === DocumentationType::Structured) {
                $result = $this->validator->handle($staging.'/'.mb_trim($project->docs_path, '/'));

                // Strict on purpose: a navigation tree listing pages that do
                // not exist is a worse reading experience than yesterday's
                // documentation.
                if ($result->failed()) {
                    throw SyncFailed::documentationInvalid($version, $result->errors());
                }
            }

            $this->snapshots->write($version, $commit, $staging);

            $pages = $this->pages($version, $type, $staging);

            $this->publisher->handle($version, $commit);

            [$indexed, $removed] = $this->index($version, $pages);

            $manifest = $this->manifest($project->docs_path, $staging);

            $version->forceFill($manifest instanceof Manifest ? [
                'redirects' => $manifest->redirects,
                'navigation' => $this->navigation($manifest, $pages),
                'manifest_title' => $manifest->title,
            ] : [
                // A README-backed version has no manifest, and any navigation
                // left from when it did must go with it.
                'redirects' => [],
                'navigation' => null,
                'manifest_title' => null,
            ])->save();

            return SyncResult::published($commit, $type, $indexed, $removed);
        } finally {
            File::deleteDirectory($staging);
        }
    }

    /**
     * Somewhere to assemble a snapshot before it is one.
     *
     * Retrieval hands over local paths and the snapshot disk may not be local,
     * so the tree is staged and then ingested. The directory is removed however
     * this ends — a failed sync should not leave the machine holding a copy of
     * every repository it could not publish.
     */
    private function staging(ProjectVersion $version, string $commit): string
    {
        $path = storage_path('app/docs-staging/'.$version->getKey().'-'.Str::substr($commit, 0, 12));

        File::deleteDirectory($path);
        File::ensureDirectoryExists($path);

        return $path;
    }

    /** `docs/` wins over the README, and neither is not an error here. */
    private function documentationType(string $docsPath, string $staging): DocumentationType
    {
        $docsPath = mb_trim($docsPath, '/');

        if (is_file("{$staging}/{$docsPath}/docs.yml")) {
            return DocumentationType::Structured;
        }

        if (is_file("{$staging}/README.md")) {
            return DocumentationType::Readme;
        }

        return DocumentationType::None;
    }

    /**
     * The page index for the new snapshot.
     *
     * Headings are taken from a render without a page context. They do not
     * depend on how a link resolves, and needing the resolution map first would
     * mean parsing every page twice to learn something the first parse knew.
     *
     * @return array<string, array{reference: string, source_path: string, title: string, description: string|null, headings: list<array{level: int, title: string, anchor: string}>, content_hash: string, content: string}>
     */
    private function pages(ProjectVersion $version, DocumentationType $type, string $staging): array
    {
        if ($type === DocumentationType::Readme) {
            $content = (string) file_get_contents("{$staging}/README.md");

            // The fallback is a single-page mode. Splitting README headings
            // into synthetic pages is explicitly not done, and
            // indexing it as `index` is what makes it routable like any other
            // version root without the router knowing which mode it is in.
            return ['index' => $this->page('README.md', 'index', $content, 'README')];
        }

        $docsPath = mb_trim($version->project->docs_path, '/');
        $pages = [];

        $files = Finder::create()->files()->in("{$staging}/{$docsPath}")->name('*.md')->sortByName();

        foreach ($files as $file) {
            $reference = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                mb_substr($file->getRelativePathname(), 0, -3),
            );

            $content = $file->getContents();
            $slug = $this->slug($reference, $this->frontMatter->parse($content));

            // Unlisted pages are indexed too: absent from the sidebar, still
            // routable and still searchable.
            $pages[$slug] = $this->page("{$docsPath}/{$reference}.md", $slug, $content, $reference);
        }

        return $pages;
    }

    /**
     * @return array{reference: string, source_path: string, title: string, description: string|null, headings: list<array{level: int, title: string, anchor: string}>, content_hash: string, content: string}
     */
    private function page(string $sourcePath, string $slug, string $content, string $reference): array
    {
        $rendered = $this->markdown->render($content);

        return [
            'reference' => $reference,
            'source_path' => $sourcePath,
            'title' => $rendered->title() ?? Str::headline(basename($slug)),
            'description' => $rendered->frontMatter?->description,
            'headings' => $rendered->headings,
            'content_hash' => hash('sha256', $content),
            'content' => $content,
        ];
    }

    /** A front-matter `slug` replaces the final segment only. */
    private function slug(string $reference, FrontMatter $frontMatter): string
    {
        if ($frontMatter->slug === null) {
            return $reference;
        }

        $directory = str_contains($reference, '/')
            ? mb_substr($reference, 0, (int) mb_strrpos($reference, '/')).'/'
            : '';

        return $directory.mb_trim($frontMatter->slug, '/');
    }

    /**
     * Write the index, hand the pages to search, and remove what is gone.
     *
     * Pruning is not optional. Without it a page deleted upstream keeps
     * resolving from stale metadata and lingers in search results, which is
     * both wrong and hard to notice.
     *
     * @param  array<string, array{reference: string, source_path: string, title: string, description: string|null, headings: list<array{level: int, title: string, anchor: string}>, content_hash: string, content: string}>  $pages
     * @return array{0: int, 1: int}
     */
    private function index(ProjectVersion $version, array $pages): array
    {
        $slugs = array_keys($pages);

        $removed = DB::transaction(function () use ($version, $pages, $slugs): array {
            $gone = $version->pages()
                ->when($slugs !== [], fn ($query) => $query->whereNotIn('slug', $slugs))
                ->pluck('slug')
                ->all();

            $version->pages()->whereIn('slug', $gone)->delete();

            foreach ($pages as $slug => $page) {
                $record = $version->pages()->updateOrCreate(['slug' => $slug], [
                    'source_path' => $page['source_path'],
                    'title' => $page['title'],
                    'description' => $page['description'],
                    'headings' => $page['headings'],
                    'content_hash' => $page['content_hash'],
                ]);

                $this->indexer->index($record, $page['content']);
            }

            return $gone;
        });

        /** @var list<string> $removed */
        $this->indexer->forget($version, $removed);

        return [count($pages), count($removed)];
    }

    /**
     * The version's parsed manifest, if it has one.
     *
     * A README-backed version has none, which is why every caller here treats
     * null as "no navigation" rather than as a failure.
     */
    private function manifest(string $docsPath, string $staging): ?Manifest
    {
        $path = "{$staging}/".mb_trim($docsPath, '/').'/docs.yml';

        if (! is_file($path)) {
            return null;
        }

        try {
            return $this->manifests->parse((string) file_get_contents($path));
        } catch (Throwable) {
            // Unreachable for structured documentation, which validated a
            // moment ago. Navigation and redirects are not worth failing a
            // published snapshot over if it somehow is.
            return null;
        }
    }

    /**
     * The navigation tree, with every page reference resolved to its URL.
     *
     * Stored resolved rather than raw. A manifest names files and a reader
     * follows URLs, and a page whose front matter moved its final segment is
     * the whole reason those differ — doing the mapping once here
     * means the sidebar is a lookup rather than a second resolution pass on
     * every request.
     *
     * Order, groups and labels are what make this worth storing at all: the
     * page index knows every page, and only the manifest knows the sequence a
     * reader moves through them in.
     *
     * @param  array<string, array{reference: string, source_path: string, title: string, description: string|null, headings: list<array{level: int, title: string, anchor: string}>, content_hash: string, content: string}>  $pages
     * @return list<array{label?: string, page?: string, children?: list<string>}>
     */
    private function navigation(Manifest $manifest, array $pages): array
    {
        $slugs = [];

        foreach ($pages as $slug => $page) {
            $slugs[$page['reference']] = $slug;
        }

        $tree = [];

        foreach ($manifest->navigation as $entry) {
            if ($entry instanceof NavigationPage) {
                $tree[] = ['page' => $slugs[$entry->reference] ?? $entry->reference];

                continue;
            }

            if ($entry instanceof NavigationGroup) {
                $tree[] = [
                    'label' => $entry->label,
                    'children' => array_map(
                        static fn (NavigationPage $child): string => $slugs[$child->reference] ?? $child->reference,
                        $entry->children,
                    ),
                ];
            }
        }

        return $tree;
    }
}
