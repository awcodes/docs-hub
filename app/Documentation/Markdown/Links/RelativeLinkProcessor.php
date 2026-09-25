<?php

declare(strict_types=1);

namespace App\Documentation\Markdown\Links;

use App\Documentation\Data\PageContext;
use App\Documentation\Support\DocumentationUrl;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Rewrites relative links and images against the project and version in hand.
 *
 * Authors write ordinary relative Markdown — `[Boundaries](architecture/boundaries.md)`
 * — and never hub URLs. That is what keeps a page readable in the repository,
 * in a pull request diff and on GitHub, and what lets a documentation fix be
 * cherry-picked between supported branches without rewriting a single link.
 *
 * Anything it does not confidently understand it leaves exactly as written. A
 * link that survives untouched is visible in the output and easy to report; a
 * link quietly rewritten to the wrong thing is a 404 someone finds months
 * later.
 */
final readonly class RelativeLinkProcessor
{
    public function __construct(
        private PageContext $context,
        private DocumentationUrl $urls = new DocumentationUrl,
    ) {}

    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Link || $node instanceof Image) {
                $node->setUrl($this->rewrite($node->getUrl()));
            }
        }
    }

    private function rewrite(string $url): string
    {
        if (! $this->isRelative($url)) {
            return $url;
        }

        [$path, $suffix] = $this->split($url);

        if ($path === '') {
            return $url;
        }

        $resolved = $this->resolve($path);

        if ($resolved === null) {
            return $url;
        }

        if (str_ends_with(mb_strtolower($resolved), '.md')) {
            $reference = $this->context->resolve(mb_substr($resolved, 0, -3));

            return $this->urls->page(
                $this->context->project,
                $this->context->version,
                $reference,
            ).$suffix;
        }

        $assetRoot = DocumentationUrl::ASSET_ROOT.'/';

        if (str_starts_with($resolved, $assetRoot)) {
            // Only the path below the asset root travels in the URL; the route
            // supplies the rest.
            return $this->urls->asset(
                $this->context->project,
                $this->context->version,
                mb_substr($resolved, mb_strlen($assetRoot)),
            ).$suffix;
        }

        return $url;
    }

    /**
     * Whether this is a link into the documentation set at all.
     *
     * An in-page anchor, an absolute path, a scheme of any kind — `https:`,
     * `mailto:`, and the `javascript:` the renderer already refuses — are all
     * somebody else's business.
     */
    private function isRelative(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return false;
        }

        return preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) !== 1;
    }

    /**
     * The path, and whatever query or fragment was hanging off it.
     *
     * A link to a heading on another page — `configuration.md#drivers` — has to
     * keep its fragment, and the fragment is not part of the file name.
     *
     * @return array{0: string, 1: string}
     */
    private function split(string $url): array
    {
        $cut = mb_strlen($url);

        foreach (['#', '?'] as $marker) {
            $at = mb_strpos($url, $marker);

            if ($at !== false) {
                $cut = min($cut, $at);
            }
        }

        return [mb_substr($url, 0, $cut), mb_substr($url, $cut)];
    }

    /**
     * A relative path made absolute within the documentation root.
     *
     * Null when it climbs out of the root. Such a link is almost always a typo,
     * and a documentation set cannot address anything above itself anyway.
     */
    private function resolve(string $path): ?string
    {
        $directory = $this->context->directory();

        $segments = [];

        foreach (explode('/', $directory === '' ? $path : "{$directory}/{$path}") as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment !== '..') {
                $segments[] = $segment;

                continue;
            }

            if ($segments === []) {
                return null;
            }

            array_pop($segments);
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}
