<?php

declare(strict_types=1);

namespace App\Documentation\Search;

use App\Documentation\Support\DocumentationUrl;
use App\Enums\VersionStatus;
use App\Models\DocumentationSection;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Full-text search across every project, or one, or one version.
 *
 * Matching is `LIKE` over the section index and scoring is done here rather
 * than by the database. For a corpus this size — a few thousand sections across
 * the whole ecosystem — that is fast, portable across every driver, and legible
 * enough that a surprising result can be explained. It is the simplest form
 * that works, and the upgrade path is a self-hosted engine behind Scout, taken when observed quality justifies it rather than before.
 */
final readonly class DocumentationSearch
{
    /** How many sections are scored before the best are returned. */
    private const int CANDIDATES = 400;

    /**
     * How many sections of one page may appear in the results.
     *
     * Without this, a query matching a page's *title* scores every section of
     * that page highly and fills the dialog with one document — which buries
     * the cross-project reach that is the reason the hub aggregates at all.
     */
    private const int PER_PAGE = 3;

    public function __construct(
        private DocumentationUrl $urls = new DocumentationUrl,
    ) {}

    /**
     * @return list<SearchResult>
     */
    public function search(
        string $query,
        ?Project $project = null,
        ?ProjectVersion $version = null,
        int $limit = 20,
    ): array {
        $terms = $this->terms($query);

        if ($terms === []) {
            return [];
        }

        $sections = $this->candidates($terms, $project, $version);

        $scored = [];

        foreach ($sections as $section) {
            $score = $this->score($section, $terms);

            if ($score > 0.0) {
                $scored[] = [$score, $section];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        return array_map(
            fn (array $hit): SearchResult => $this->result($hit[1], $hit[0], $terms),
            $this->spread($scored, $limit),
        );
    }

    /**
     * The best results, without letting one page take them all.
     *
     * Order is preserved, so the strongest hit still leads; only a page's
     * fourth section and beyond are held back.
     *
     * @param  list<array{0: float, 1: DocumentationSection}>  $scored
     * @return list<array{0: float, 1: DocumentationSection}>
     */
    private function spread(array $scored, int $limit): array
    {
        $taken = [];
        $results = [];

        foreach ($scored as $hit) {
            $page = $hit[1]->documentation_page_id;
            $taken[$page] = ($taken[$page] ?? 0) + 1;

            if ($taken[$page] > self::PER_PAGE) {
                continue;
            }

            $results[] = $hit;

            if (count($results) === $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Sections that contain every term somewhere.
     *
     * Every term rather than any: a two-word query almost always means both
     * words, and an `OR` over a documentation set returns the whole set.
     *
     * @param  list<string>  $terms
     * @return list<DocumentationSection>
     */
    private function candidates(array $terms, ?Project $project, ?ProjectVersion $version): array
    {
        $query = DocumentationSection::query()
            ->with(['page', 'version.project'])
            ->whereHas('version', function (Builder $query): void {
                // Only what is actually published. A version mid-sync, or one
                // registered and never synchronized, has nothing a reader can
                // open.
                $query->whereNotNull('active_snapshot')
                    ->whereRelation('project', 'is_visible', true);
            });

        foreach ($terms as $term) {
            $escaped = addcslashes($term, '%_\\');

            $query->where(function (Builder $query) use ($escaped): void {
                $query->where('content', 'like', "%{$escaped}%")
                    ->orWhere('heading', 'like', "%{$escaped}%")
                    ->orWhereRelation('page', 'title', 'like', "%{$escaped}%");
            });
        }

        if ($version instanceof ProjectVersion) {
            $query->where('project_version_id', $version->getKey());
        } elseif ($project instanceof Project) {
            $query->whereRelation('version', 'project_id', $project->getKey());
        }

        /** @var list<DocumentationSection> $sections */
        $sections = $query->limit(self::CANDIDATES)->get()->all();

        return $sections;
    }

    /**
     * Where a term matched decides how much it counts.
     *
     * A page title is the strongest signal, a heading next, the body last —
     * and current documentation is boosted hard, because a reader who searches
     * without naming a version almost always means the one in use. Legacy stays
     * searchable and ranks below everything.
     *
     * @param  list<string>  $terms
     */
    private function score(DocumentationSection $section, array $terms): float
    {
        $title = mb_strtolower($section->page->title);
        $heading = mb_strtolower((string) $section->heading);
        $content = mb_strtolower($section->content);

        $score = 0.0;

        foreach ($terms as $term) {
            $matched = false;

            if (str_contains($title, $term)) {
                $score += 8.0;
                $matched = true;
            }

            if (str_contains($heading, $term)) {
                $score += 4.0;
                $matched = true;
            }

            $occurrences = mb_substr_count($content, $term);

            if ($occurrences > 0) {
                // Diminishing: a term appearing forty times means the section
                // is about it, not that it is forty times more relevant.
                $score += min(3.0, 1.0 + ($occurrences * 0.25));
                $matched = true;
            }

            if (! $matched) {
                return 0.0;
            }
        }

        return $score * $this->statusWeight($section->version->status);
    }

    private function statusWeight(VersionStatus $status): float
    {
        return match ($status) {
            VersionStatus::Current => 1.0,
            VersionStatus::Supported => 0.6,
            VersionStatus::Beta => 0.5,
            VersionStatus::Legacy => 0.25,
        };
    }

    /** @param list<string> $terms */
    private function result(DocumentationSection $section, float $score, array $terms): SearchResult
    {
        $version = $section->version;
        $project = $version->project;

        return new SearchResult(
            projectName: $project->name,
            version: $version->version,
            status: $version->status,
            showsVersion: $project->versioning_mode->showsVersionSegment(),
            pageTitle: $section->page->title,
            heading: $section->heading,
            excerpt: $this->excerpt($section->content, $terms),
            url: $this->urls->page($project, $version, $section->page->slug)
                .($section->anchor === null ? '' : "#{$section->anchor}"),
            score: $score,
        );
    }

    /**
     * A window of the section around the first term that matched.
     *
     * Taken from the Markdown with its decoration stripped, so a result reads
     * as prose rather than as source.
     *
     * @param  list<string>  $terms
     */
    private function excerpt(string $content, array $terms, int $length = 180): string
    {
        $plain = $this->plain($content);
        $at = mb_stripos($plain, $terms[0]);
        $start = $at === false ? 0 : max(0, $at - 60);

        $excerpt = mb_substr($plain, $start, $length);

        if ($start > 0) {
            $excerpt = '…'.mb_substr($excerpt, (int) mb_strpos($excerpt, ' ') + 1);
        }

        return mb_trim($excerpt).(mb_strlen($plain) > $start + $length ? '…' : '');
    }

    private function plain(string $content): string
    {
        $content = preg_replace('/```.*?```/s', ' ', $content) ?? $content;
        $content = preg_replace('/^\s*>\s?\[![A-Z]+\]\s*$/m', '', $content) ?? $content;
        $content = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $content) ?? $content;
        $content = preg_replace('/[`*_>#|]+/', '', $content) ?? $content;

        return mb_trim((string) preg_replace('/\s+/', ' ', $content));
    }

    /**
     * The query as terms, lowercased, with noise dropped.
     *
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $parts = preg_split('/\s+/', mb_strtolower(mb_trim($query))) ?: [];

        return array_values(array_filter(
            $parts,
            static fn (string $term): bool => mb_strlen($term) >= 2,
        ));
    }
}
