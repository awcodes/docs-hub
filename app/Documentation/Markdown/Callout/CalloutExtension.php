<?php

declare(strict_types=1);

namespace App\Documentation\Markdown\Callout;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * GitHub alert callouts, as a CommonMark extension.
 *
 * An extension rather than a post-processing pass over rendered HTML: the
 * alternative would mean re-parsing the hub's own output to find things the
 * parser already knew.
 */
final readonly class CalloutExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addEventListener(DocumentParsedEvent::class, new CalloutProcessor)
            ->addRenderer(Callout::class, new CalloutRenderer);
    }
}
