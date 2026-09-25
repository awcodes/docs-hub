<?php

declare(strict_types=1);

namespace App\Documentation\Markdown;

use App\Documentation\Data\RenderedPage;

/**
 * Cuts a page into the sections search indexes.
 *
 * Split on the Markdown rather than on the rendered HTML: the source is what a
 * reader typed, and re-parsing our own output to recover the structure the
 * parser already knew would be the same mistake the renderer avoids for
 * headings.
 *
 * Fenced code stays in. `withoutAuthentication()` is exactly the kind of thing
 * somebody searches a documentation set for, and stripping code blocks would
 * make the terms most worth finding the ones that could not be found.
 */
final readonly class SectionSplitter
{
    /**
     * @return list<array{heading: ?string, anchor: ?string, level: int, position: int, content: string}>
     */
    public function split(string $markdown, RenderedPage $rendered): array
    {
        $anchors = $this->anchors($rendered);

        $sections = [];
        $current = ['heading' => null, 'anchor' => null, 'level' => 0, 'lines' => []];
        $fenced = false;

        foreach (explode("\n", $this->withoutFrontMatter($markdown)) as $line) {
            // A `#` inside a fence is a shell comment or a CSS id, not a
            // heading, so fences are tracked rather than assumed away.
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $fenced = ! $fenced;
            }

            if (! $fenced && preg_match('/^(#{1,6})\s+(.*\S)\s*$/', $line, $matches) === 1) {
                $sections[] = $current;

                $heading = $this->plain($matches[2]);
                $level = mb_strlen($matches[1]);

                // An `h1` is the page's title, not a section within it. Indexed
                // as a heading it would render a breadcrumb reading
                // "Theme composition › Theme composition", and the title is
                // already scored higher than any heading anyway.
                $isTitle = $level === 1;

                $current = [
                    'heading' => $isTitle ? null : $heading,
                    'anchor' => $isTitle ? null : ($anchors[$heading] ?? null),
                    'level' => $isTitle ? 0 : $level,
                    'lines' => [],
                ];

                continue;
            }

            $current['lines'][] = $line;
        }

        $sections[] = $current;

        $indexed = [];
        $position = 0;

        foreach ($sections as $section) {
            $content = mb_trim(implode("\n", $section['lines']));

            // A heading with no body is kept: `## Requirements` followed
            // straight by a sub-heading is a real section, and a reader
            // searching for "requirements" should find it. What is dropped is
            // an empty lead — text before the first heading, when there is
            // none — which has neither a heading to match nor a body.
            if ($content === '' && $section['heading'] === null) {
                continue;
            }

            $indexed[] = [
                'heading' => $section['heading'],
                'anchor' => $section['anchor'],
                'level' => $section['level'],
                'position' => $position++,
                'content' => $content,
            ];
        }

        return $indexed;
    }

    /**
     * Heading text to the anchor the renderer gave it.
     *
     * Taken from the render rather than re-slugged, for the same reason the
     * table of contents is: two slug implementations that agree today are two
     * that can disagree later, and a search result that deep-links one
     * character off is a miserable thing to debug.
     *
     * @return array<string, string>
     */
    private function anchors(RenderedPage $rendered): array
    {
        $anchors = [];

        foreach ($rendered->headings as $heading) {
            $anchors[$heading['title']] = $heading['anchor'];
        }

        return $anchors;
    }

    private function withoutFrontMatter(string $markdown): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $markdown);

        if (! str_starts_with($normalized, "---\n")) {
            return $normalized;
        }

        $end = mb_strpos($normalized, "\n---", 3);

        return $end === false ? $normalized : mb_substr($normalized, $end + 4);
    }

    /** Heading text as a reader sees it, without its Markdown decoration. */
    private function plain(string $heading): string
    {
        $heading = preg_replace('/`([^`]*)`/', '$1', $heading) ?? $heading;
        $heading = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $heading) ?? $heading;
        $heading = preg_replace('/[*_]{1,3}([^*_]+)[*_]{1,3}/', '$1', $heading) ?? $heading;

        return mb_trim($heading);
    }
}
