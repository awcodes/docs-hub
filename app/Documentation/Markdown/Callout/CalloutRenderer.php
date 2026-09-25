<?php

declare(strict_types=1);

namespace App\Documentation\Markdown\Callout;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Xml\XmlNodeRendererInterface;

/**
 * Renders a callout as a labelled region.
 *
 * The label is a real element rather than a CSS pseudo-element, because a
 * reader using a screen reader needs to hear "Warning" before the sentence it
 * qualifies, and generated content is not reliably announced.
 *
 * Presentation stops here. Colour, iconography and spacing belong to the
 * documentation frontend's stylesheet, which is the same boundary drawn around
 * Phiki.
 */
final class CalloutRenderer implements NodeRendererInterface, XmlNodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        Callout::assertInstanceOf($node);

        /** @var Callout $node */
        $attributes = $node->data->getData('attributes');

        $attributes->append('class', 'callout callout-'.$node->type->value);

        return new HtmlElement('div', $attributes->export(), [
            new HtmlElement('p', ['class' => 'callout-label'], $node->type->label()),
            new HtmlElement('div', ['class' => 'callout-body'], $childRenderer->renderNodes($node->children())),
        ]);
    }

    public function getXmlTagName(Node $node): string
    {
        return 'callout';
    }

    /** @return array<string, scalar> */
    public function getXmlAttributes(Node $node): array
    {
        Callout::assertInstanceOf($node);

        /** @var Callout $node */
        return ['type' => $node->type->value];
    }
}
