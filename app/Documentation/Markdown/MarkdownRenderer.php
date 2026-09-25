<?php

declare(strict_types=1);

namespace App\Documentation\Markdown;

use App\Documentation\Data\PageContext;
use App\Documentation\Data\RenderedPage;
use App\Documentation\Markdown\Callout\CalloutExtension;
use App\Documentation\Markdown\Links\RelativeLinkExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Phiki\Adapters\CommonMark\PhikiExtension;
use Phiki\Theme\Theme;

/**
 * Markdown to HTML, with the headings the page turned out to have.
 *
 * Parsed and rendered in two steps rather than through `MarkdownConverter`,
 * because the document itself is wanted: the parser has already worked out
 * every heading and its anchor, and recovering them from the rendered HTML
 * afterwards would be reparsing the hub's own output to learn something it
 * knew a moment earlier.
 *
 * Relative links resolve against a `PageContext` when one is given, and are
 * left alone when it is not. Rendering a page for a reader always has that
 * context; validating a repository from a plain checkout never does, and must
 * not need a registry to render a paragraph.
 */
final readonly class MarkdownRenderer
{
    public function render(string $markdown, ?PageContext $context = null): RenderedPage
    {
        $environment = $this->environment($context);

        $parser = new MarkdownParser($environment);
        $document = $parser->parse($markdown);

        $html = new HtmlRenderer($environment)->renderDocument($document)->getContent();

        return new RenderedPage(
            html: $html,
            headings: $this->headings($document),
            frontMatter: (new FrontMatterParser)->parse($markdown),
        );
    }

    /** The hub's own host, so its own links are not treated as leaving it. */
    private function internalHost(): string
    {
        /** @var string $url */
        $url = config('app.url', '');

        return parse_url($url, PHP_URL_HOST) ?: $url;
    }

    private function environment(?PageContext $context): Environment
    {
        $environment = new Environment([
            /*
             | Documented repositories are the widest write surface in the
             | system, and the hub aggregates them onto one origin that shares
             | a session with the admin panel. Rendering raw HTML would hand
             | every contributor to every repository a script tag on that
             | origin.
             |
             | The cost is real: `<br>`, `<kbd>` and `<details>` are escaped
             | too. Cover those with extensions and callouts rather than opening
             | this, and if something concrete ever forces the question, run a
             | sanitizer over a constrained tag set instead of switching to
             | `allow`.
             */
            'html_input' => 'escape',
            'allow_unsafe_links' => false,

            'heading_permalink' => [
                // Clean anchors: `#one-filename-three-jobs`, which is what a
                // stored heading index and a hand-written `#` link both expect.
                'id_prefix' => '',
                'fragment_prefix' => '',
                'symbol' => '',
                'aria_hidden' => true,
                'insert' => 'after',
            ],

            'external_link' => [
                // The host, not the URL. Handed the whole `APP_URL` this
                // matches nothing, and every link on the site is quietly
                // labelled external.
                'internal_hosts' => $this->internalHost(),
                'open_in_new_window' => true,
                'html_class' => 'external-link',
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment
            ->addExtension(new CommonMarkCoreExtension)
            // Front matter is a block the renderer must not print. The parser
            // reads the same bytes for metadata.
            ->addExtension(new FrontMatterExtension)
            // GitHub-flavoured, minus the raw-HTML pieces `html_input` already
            // settles.
            ->addExtension(new TableExtension)
            ->addExtension(new StrikethroughExtension)
            ->addExtension(new TaskListExtension)
            ->addExtension(new AutolinkExtension)
            ->addExtension(new HeadingPermalinkExtension)
            ->addExtension(new ExternalLinkExtension)
            ->addExtension(new CalloutExtension)
            // Server-side, through Phiki's own adapter rather than a pass over
            // the rendered HTML, and never a JavaScript runtime.
            ->addExtension(new PhikiExtension([
                'light' => Theme::GithubLight,
                'dark' => Theme::GithubDark,
            ]));

        if ($context instanceof PageContext) {
            $environment->addExtension(new RelativeLinkExtension($context));
        }

        return $environment;
    }

    /**
     * Every heading, in document order, with the anchor it was given.
     *
     * The anchor is read off the permalink node the parser attached rather than
     * re-slugged here. Two slug implementations that agree today are two that
     * can disagree later, and a table of contents whose links miss by one
     * character is a particularly annoying bug to find.
     *
     * @return list<array{level: int, title: string, anchor: string}>
     */
    private function headings(Document $document): array
    {
        $headings = [];

        foreach ($document->iterator() as $node) {
            if (! $node instanceof Heading) {
                continue;
            }

            $anchor = null;
            $title = '';

            foreach ($node->iterator() as $child) {
                if ($child instanceof HeadingPermalink) {
                    $anchor = $child->getSlug();

                    continue;
                }

                if ($child instanceof StringContainerInterface) {
                    $title .= $child->getLiteral();
                }
            }

            $title = mb_trim($title);

            if ($anchor === null || $title === '') {
                continue;
            }

            $headings[] = [
                'level' => $node->getLevel(),
                'title' => $title,
                'anchor' => $anchor,
            ];
        }

        return $headings;
    }
}
