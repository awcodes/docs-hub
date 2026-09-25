<?php

declare(strict_types=1);

namespace App\Documentation\Markdown\Links;

use App\Documentation\Data\PageContext;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Relative link resolution, as a CommonMark extension.
 *
 * Registered at a high priority so it runs before the external-link extension.
 * Order is not load-bearing today — a rewritten link is still host-less and
 * still internal — but the two disagreeing about what "external" means is the
 * kind of thing that only shows up as a stray `target="_blank"`.
 */
final readonly class RelativeLinkExtension implements ExtensionInterface
{
    public function __construct(private PageContext $context) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(
            DocumentParsedEvent::class,
            new RelativeLinkProcessor($this->context),
            priority: 100,
        );
    }
}
