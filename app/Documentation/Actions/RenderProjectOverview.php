<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Documentation\Data\RenderedPage;
use App\Models\DocumentationPage;
use App\Models\Project;
use App\Models\ProjectVersion;

/**
 * A project's landing page, rendered for display on another site.
 *
 * The default version's `index` page, with two changes that only matter once
 * the HTML leaves the hub. The leading `<h1>` goes, because the embedding page
 * shows its own title. Site-relative links and assets become absolute, because
 * `/curator/5.x/installation` resolves against whichever host is rendering it.
 *
 * Null when there is nothing published to show yet. The caller keeps whatever
 * it had rather than replacing an overview with an empty one.
 */
final readonly class RenderProjectOverview
{
    public function __construct(
        private RenderDocumentationPage $render = new RenderDocumentationPage,
    ) {}

    /** @return array{title: string, description: string|null, html: string}|null */
    public function handle(Project $project): ?array
    {
        $version = $project->defaultVersion;

        if (! $version instanceof ProjectVersion || ! $version->isPublished()) {
            return null;
        }

        $page = $version->pages()->where('slug', 'index')->first();

        if (! $page instanceof DocumentationPage) {
            return null;
        }

        $rendered = $this->render->handle($page);

        if (! $rendered instanceof RenderedPage) {
            return null;
        }

        return [
            'title' => $rendered->title() ?? $project->name,
            'description' => $rendered->frontMatter->description ?? $project->description,
            'html' => $this->absolute($this->withoutTitle($rendered->html)),
        ];
    }

    /** Only a heading that opens the page; a later `<h1>` is content. */
    private function withoutTitle(string $html): string
    {
        return mb_ltrim((string) preg_replace('/^\s*<h1\b[^>]*>.*?<\/h1>/s', '', $html, 1));
    }

    /**
     * Prefixes every site-relative `href` and `src` with the hub's own URL.
     *
     * Protocol-relative `//host` URLs are already absolute and are left alone.
     * Fragments ride along untouched, which matters: `#gh-dark-mode-only` on an
     * image is what decides which theme shows it.
     */
    private function absolute(string $html): string
    {
        $base = mb_rtrim((string) config('app.url'), '/');

        return (string) preg_replace('/\b(href|src)="\/(?!\/)/', '$1="'.$base.'/', $html);
    }
}
