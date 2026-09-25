<?php

declare(strict_types=1);

namespace App\Documentation\Markdown;

use App\Documentation\Data\FrontMatter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the optional `---` block at the top of a documentation page.
 *
 * Split by hand rather than through CommonMark's front matter extension: this
 * runs during validation and indexing, where the body is not wanted and a
 * rendering pipeline is not available — `docs:validate` has to work against a
 * plain checkout with no hub behind it.
 */
final class FrontMatterParser
{
    /**
     * @throws ParseException when the block is present but not valid YAML
     */
    public function parse(string $markdown): FrontMatter
    {
        $block = $this->block($markdown);

        if ($block === null) {
            return new FrontMatter;
        }

        $values = Yaml::parse($block);

        if (! is_array($values) || array_is_list($values)) {
            return new FrontMatter;
        }

        return new FrontMatter(
            title: $this->string($values, 'title'),
            description: $this->string($values, 'description'),
            slug: $this->string($values, 'slug'),
        );
    }

    /**
     * The YAML between the opening and closing fences, if there is one.
     *
     * The opening fence has to be the very first thing in the file. A `---`
     * further down is a horizontal rule, and a document that happens to contain
     * one is not a document with front matter.
     */
    private function block(string $markdown): ?string
    {
        // A leading BOM would otherwise make the fence not the first thing.
        $markdown = preg_replace('/^\x{FEFF}/u', '', $markdown) ?? $markdown;
        $normalized = str_replace(["\r\n", "\r"], "\n", $markdown);

        if (! str_starts_with($normalized, "---\n")) {
            return null;
        }

        $end = mb_strpos($normalized, "\n---", 3);

        if ($end === false) {
            return null;
        }

        return mb_substr($normalized, 4, $end - 3);
    }

    /** @param array<mixed> $values */
    private function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
