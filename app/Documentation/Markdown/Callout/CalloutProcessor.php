<?php

declare(strict_types=1);

namespace App\Documentation\Markdown\Callout;

use App\Enums\CalloutType;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Turns `> [!NOTE]` blockquotes into callouts, after parsing.
 *
 * Done on the tree rather than with a block parser on purpose. GitHub's alert
 * syntax *is* a blockquote — that is the whole reason it degrades gracefully
 * everywhere else — so the marker can only be recognized once the blockquote
 * has been parsed as one. Anything unrecognized is left exactly as it was, and
 * renders as the blockquote it always was.
 */
final readonly class CalloutProcessor
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if (! $node instanceof BlockQuote) {
                continue;
            }

            $type = $this->markerOf($node);

            if (! $type instanceof CalloutType) {
                continue;
            }

            $callout = new Callout($type);

            foreach ($node->children() as $child) {
                $callout->appendChild($child);
            }

            $node->replaceWith($callout);
        }
    }

    /**
     * The alert type, if this blockquote opens with a marker and nothing else
     * on that line.
     *
     * Reads the parsed inlines rather than the raw source, so a blockquote that
     * merely mentions `[!NOTE]` mid-sentence is not promoted. The marker is
     * consumed here: leaving it in place would print it into the body.
     */
    private function markerOf(BlockQuote $quote): ?CalloutType
    {
        $paragraph = $quote->firstChild();

        if (! $paragraph instanceof Paragraph) {
            return null;
        }

        $marker = $paragraph->firstChild();

        if (! $marker instanceof Text) {
            return null;
        }

        if (preg_match('/^\[!([A-Za-z]+)\]$/', mb_trim($marker->getLiteral()), $matches) !== 1) {
            return null;
        }

        $type = CalloutType::fromAlert($matches[1]);

        if (! $type instanceof CalloutType) {
            return null;
        }

        // The marker occupies its own line, so the newline after it goes too —
        // otherwise the body opens with a blank first line.
        $next = $marker->next();

        if ($next instanceof Newline) {
            $next->detach();
        }

        $marker->detach();

        // `> [!NOTE]` with nothing under it is a marker and no content. There
        // is no callout to make, and promoting it would render an empty box.
        if (! $paragraph->firstChild() instanceof Node) {
            $paragraph->detach();
        }

        return $quote->firstChild() instanceof Node ? $type : null;
    }
}
