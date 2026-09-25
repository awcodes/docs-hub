<?php

declare(strict_types=1);

namespace App\Documentation\Markdown\Callout;

use App\Enums\CalloutType;
use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A blockquote that turned out to be a GitHub alert.
 *
 * A node rather than rewritten HTML, so that the children keep being ordinary
 * Markdown — a callout containing a list, a code block or a link is parsed by
 * CommonMark and not by this.
 */
final class Callout extends AbstractBlock
{
    public function __construct(public readonly CalloutType $type)
    {
        parent::__construct();
    }
}
