<?php

declare(strict_types=1);

namespace App\Documentation\Markdown;

use App\Documentation\Data\DocumentationPageMetadata;
use App\Documentation\Data\ParsedDocumentationPage;
use App\Documentation\Exceptions\DocumentationValidationException;
use Illuminate\Support\Str;

final class MarkdownPageParser
{
    public function parseFile(string $path, DocumentationPageMetadata $metadata): ParsedDocumentationPage
    {
        $markdown = file_get_contents($path);

        if ($markdown === false) {
            throw new DocumentationValidationException(["Unable to read Markdown file [{$path}]."]);
        }

        $content = preg_replace('/\A---\R.*?\R---(?:\R|\z)/s', '', $markdown, 1) ?? $markdown;
        preg_match_all('/^(#{1,6})\h+(.+?)\h*#*\h*$/m', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $headings = [];
        $sections = [];
        $headingPath = [];
        $anchorCounts = [];
        $firstHeadingOffset = $matches[0][0][1] ?? mb_strlen($content);
        $lead = mb_trim(mb_substr($content, 0, $firstHeadingOffset));

        if ($lead !== '') {
            $sections[] = [
                'heading_path' => [],
                'heading' => null,
                'anchor' => null,
                'body' => $lead,
                'sort_order' => 0,
            ];
        }

        foreach ($matches as $index => $match) {
            $level = mb_strlen($match[1][0]);
            $title = mb_trim($match[2][0]);
            $baseAnchor = Str::slug($title);
            $occurrence = $anchorCounts[$baseAnchor] ?? 0;
            $anchorCounts[$baseAnchor] = $occurrence + 1;
            $anchor = $occurrence === 0 ? $baseAnchor : "{$baseAnchor}-{$occurrence}";
            $headingPath = array_slice($headingPath, 0, max(0, $level - 1));
            $headingPath[$level - 1] = $title;
            $headingPath = array_values(array_filter($headingPath, is_string(...)));
            $sectionStart = $match[0][1] + mb_strlen($match[0][0]);
            $sectionEnd = $matches[$index + 1][0][1] ?? mb_strlen($content);

            $headings[] = ['level' => $level, 'title' => $title, 'anchor' => $anchor];
            $sections[] = [
                'heading_path' => $headingPath,
                'heading' => $title,
                'anchor' => $anchor,
                'body' => mb_trim(mb_substr($content, $sectionStart, $sectionEnd - $sectionStart)),
                'sort_order' => count($sections),
            ];
        }

        $title = $metadata->title
            ?? ($headings[0]['title'] ?? Str::of(pathinfo($path, PATHINFO_FILENAME))->headline()->toString());

        return new ParsedDocumentationPage(
            title: $title,
            headings: $headings,
            sections: $sections,
            contentHash: hash('sha256', $markdown),
        );
    }
}
