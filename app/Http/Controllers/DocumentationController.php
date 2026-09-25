<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Documentation\Actions\RenderDocumentationPage;
use App\Documentation\Data\RenderedPage;
use App\Documentation\Navigation\NavigationTree;
use App\Documentation\Support\DocumentationUrl;
use App\Documentation\Support\VersionResolver;
use App\Documentation\Support\VersionSwitch;
use App\Models\DocumentationPage;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves one documentation page.
 *
 * The routing rule lives in `VersionResolver` rather than here, because it is
 * a registry question — is this segment a version? — and not a
 * pattern the URL can answer about itself.
 */
final class DocumentationController extends Controller
{
    /** How far a redirect may chain before the router stops following it. */
    private const int REDIRECT_HOPS = 5;

    public function __construct(
        private readonly VersionResolver $versions = new VersionResolver,
        private readonly DocumentationUrl $urls = new DocumentationUrl,
        private readonly RenderDocumentationPage $render = new RenderDocumentationPage,
        private readonly NavigationTree $navigation = new NavigationTree,
        private readonly VersionSwitch $versionSwitch = new VersionSwitch,
    ) {}

    public function __invoke(Project $project, string $path = ''): View|RedirectResponse
    {
        $resolution = $this->versions->resolve($project, $path);

        if (! $resolution->canonical) {
            // The versioned URL is always canonical. Rendering here instead
            // would mean a shared link silently changed meaning the day a new
            // version became the default.
            return redirect(
                $this->urls->page($project, $resolution->version, $resolution->page),
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        $version = $resolution->version;

        abort_unless($version->isPublished(), Response::HTTP_NOT_FOUND);

        $page = $this->page($version, $resolution->page);

        if (! $page instanceof DocumentationPage) {
            // The repository's own redirect map, consulted before giving up
            // . It covers what a front-matter `slug`
            // cannot: a page that moved between directories, was split, or was
            // merged into another.
            $moved = $this->redirect($version, $resolution->page);

            abort_if($moved === null, Response::HTTP_NOT_FOUND);

            return redirect($moved, Response::HTTP_MOVED_PERMANENTLY);
        }

        $rendered = $this->render->handle($page);

        abort_if(! $rendered instanceof RenderedPage, Response::HTTP_NOT_FOUND);

        return view('documentation.page', [
            'project' => $project,
            'version' => $version,
            'page' => $page,
            'rendered' => $rendered,
            'navigation' => $this->navigation->sidebar($version, $page),
            'neighbours' => $this->navigation->neighbours($version, $page),
            'source' => $this->sourceUrl($project, $version, $page),
            'versions' => $project->versioning_mode->showsVersionSegment()
                ? $this->versionSwitch->options($version, $page)
                : [],
            'currentVersion' => $this->versionSwitch->currentVersion($version, $page),
            'projects' => $this->projects($project),
        ]);
    }

    /**
     * Where a missing page moved to, if the manifest says.
     *
     * Chains are followed for a few hops and no further: a redirect map is
     * written by hand, and a cycle in one should cost a 404 rather than a
     * request that never returns.
     */
    private function redirect(ProjectVersion $version, string $reference): ?string
    {
        $redirects = $version->redirects ?? [];
        $seen = [];

        for ($hop = 0; $hop < self::REDIRECT_HOPS; $hop++) {
            if (! isset($redirects[$reference]) || in_array($reference, $seen, true)) {
                break;
            }

            $seen[] = $reference;
            $reference = $redirects[$reference];
        }

        return $version->pages()->where('slug', $reference)->exists()
            ? $this->urls->page($version->project, $version, $reference)
            : null;
    }

    /**
     * Every project a reader may switch to, in its homepage groups.
     *
     * Hidden projects are absent: `is_visible` exists so that a project being
     * set up is reachable by direct URL without appearing finished.
     * The project being read is included, so the selector shows
     * where the reader is rather than only where they could go.
     *
     * @return array<string, list<array{name: string, url: string, current: bool}>>
     */
    private function projects(Project $current): array
    {
        return Project::query()
            ->visible()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group')
            ->map(fn ($projects) => $projects->map(fn (Project $project): array => [
                'name' => $project->name,
                'url' => '/'.$project->slug,
                'current' => $project->is($current),
            ])->all())
            ->all();
    }

    /**
     * Where this page is edited, on GitHub, at the ref it was read from.
     *
     * An editing affordance rather than a reading one: the repositories are
     * private, so it resolves only for readers with access, and the layout
     * presents it as an aside rather than part of the reading path.
     */
    private function sourceUrl(Project $project, ProjectVersion $version, DocumentationPage $page): string
    {
        return sprintf(
            'https://github.com/%s/blob/%s/%s',
            $project->repository,
            $version->git_ref,
            $page->source_path,
        );
    }

    /**
     * The page a URL names.
     *
     * An empty path is the version root, which renders `index` — the landing
     * page is the version's own `index.md` rather than separate marketing
     * content kept in the hub. A README-backed version indexes its
     * single page the same way, so nothing here has to know which it is.
     */
    private function page(ProjectVersion $version, string $reference): ?DocumentationPage
    {
        return $version->pages()
            ->where('slug', $reference === '' ? 'index' : $reference)
            ->first();
    }
}
