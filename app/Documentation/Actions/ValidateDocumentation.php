<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Documentation\Data\FrontMatter;
use App\Documentation\Data\Manifest;
use App\Documentation\Data\ValidationIssue;
use App\Documentation\Data\ValidationResult;
use App\Documentation\Exceptions\InvalidManifest;
use App\Documentation\Markdown\FrontMatterParser;
use App\Documentation\Navigation\ManifestParser;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Checks a documentation root the way the synchronizer will, before it has to.
 *
 * Publication is strict: a navigation entry naming a file that does not exist
 * blocks the whole version's documentation, and the previous snapshot keeps
 * serving. That is only defensible because this runs in the
 * repository's own CI, where a typo costs a pull request comment instead of
 * quietly stopping every documentation update on a branch.
 *
 * So it takes a path and nothing else. No registry, no database, no network —
 * the same contract as the local filesystem source, and what lets a pull
 * request check run it from a plain checkout.
 */
final readonly class ValidateDocumentation
{
    public function __construct(
        private ManifestParser $manifests = new ManifestParser,
        private FrontMatterParser $frontMatter = new FrontMatterParser,
    ) {}

    public function handle(string $documentationRoot): ValidationResult
    {
        $root = mb_rtrim($documentationRoot, '/');

        if (! is_dir($root)) {
            return new ValidationResult([
                ValidationIssue::error("There is no documentation root at `{$root}`."),
            ]);
        }

        $manifestPath = $root.'/docs.yml';

        if (! is_file($manifestPath)) {
            return new ValidationResult([
                ValidationIssue::error('A documentation root must contain `docs.yml`.', 'docs.yml'),
            ]);
        }

        try {
            $manifest = $this->manifests->parse((string) file_get_contents($manifestPath));
        } catch (InvalidManifest $exception) {
            // Nothing further is checkable: without navigation there are no
            // references to resolve and no reading order to walk.
            return new ValidationResult([
                ValidationIssue::error($exception->getMessage(), 'docs.yml'),
            ]);
        }

        [$pages, $unreadable] = $this->pages($root);

        return new ValidationResult([
            ...$unreadable,
            ...$this->urlIssues($pages),
            ...$this->navigationIssues($manifest, array_keys($pages)),
            ...$this->redirectIssues($manifest, array_keys($pages)),
        ]);
    }

    /**
     * Every Markdown page under the root, keyed by its reference, and the
     * issues found reading them.
     *
     * Unlisted files are gathered on purpose. A page absent from `docs.yml` is
     * routable and searchable and simply not in the sidebar, so
     * it still has to hold up its end of slug uniqueness.
     *
     * @return array{0: array<string, FrontMatter>, 1: list<ValidationIssue>}
     */
    private function pages(string $root): array
    {
        $pages = [];
        $issues = [];

        $files = Finder::create()->files()->in($root)->name('*.md')->sortByName();

        foreach ($files as $file) {
            $reference = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                mb_substr($file->getRelativePathname(), 0, -3),
            );

            try {
                $pages[$reference] = $this->frontMatter->parse($file->getContents());
            } catch (ParseException $exception) {
                $issues[] = ValidationIssue::error(
                    'The front matter is not valid YAML: '.$exception->getMessage(),
                    "{$reference}.md",
                );

                // Kept in the list with empty metadata, so a navigation entry
                // pointing at it still resolves and the author is told about
                // one problem rather than two.
                $pages[$reference] = new FrontMatter;
            }
        }

        return [$pages, $issues];
    }

    /**
     * Two pages must not resolve to one URL.
     *
     * A `slug` must be unique within a version. Checked
     * against the whole resolved reference rather than the final segment alone,
     * because the segment is only half of what decides the URL: an
     * `architecture/overview` and a `usage/overview` are two perfectly ordinary
     * pages, while two files claiming `installation` are a page that cannot be
     * served.
     *
     * @param  array<string, FrontMatter>  $pages
     * @return list<ValidationIssue>
     */
    private function urlIssues(array $pages): array
    {
        $issues = [];

        /** @var array<string, list<string>> $claims */
        $claims = [];

        foreach ($pages as $reference => $frontMatter) {
            $claims[$this->resolvedReference($reference, $frontMatter)][] = $reference;
        }

        foreach ($claims as $url => $claimants) {
            if (count($claimants) > 1) {
                $issues[] = ValidationIssue::error(
                    "`{$url}` is claimed by more than one page: ".implode(', ', $claimants).'.',
                    'docs.yml',
                );
            }

            // A top-level page whose slug looks like a version
            // string is unreachable through its unversioned URL, because the
            // router reads that segment as a version first.
            if (! str_contains($url, '/') && $this->looksLikeAVersion($url)) {
                $issues[] = ValidationIssue::warning(
                    "`{$url}` looks like a version string, so it is unreachable at `/{project}/{$url}`.",
                    $claimants[0].'.md',
                );
            }
        }

        return $issues;
    }

    /**
     * @param  list<string>  $references
     * @return list<ValidationIssue>
     */
    private function navigationIssues(Manifest $manifest, array $references): array
    {
        $issues = [];
        $seen = [];

        foreach ($manifest->pages() as $page) {
            if (! in_array($page->reference, $references, true)) {
                $issues[] = ValidationIssue::error(
                    "Navigation lists `{$page->reference}`, but `{$page->reference}.md` does not exist.",
                    'docs.yml',
                );

                continue;
            }

            if (in_array($page->reference, $seen, true)) {
                // Not fatal, but previous/next has to pick one of them, and
                // whichever it picks will look like a bug to a reader.
                $issues[] = ValidationIssue::warning(
                    "Navigation lists `{$page->reference}` more than once.",
                    'docs.yml',
                );
            }

            $seen[] = $page->reference;
        }

        return $issues;
    }

    /**
     * @param  list<string>  $references
     * @return list<ValidationIssue>
     */
    private function redirectIssues(Manifest $manifest, array $references): array
    {
        $issues = [];

        foreach ($manifest->redirects as $from => $to) {
            if (! in_array($to, $references, true)) {
                $issues[] = ValidationIssue::error(
                    "Redirect `{$from}` points at `{$to}`, which does not exist.",
                    'docs.yml',
                );
            }

            // A redirect the router will never reach, because the page it is
            // supposed to stand in for is still there.
            if (in_array($from, $references, true)) {
                $issues[] = ValidationIssue::warning(
                    "Redirect `{$from}` is shadowed by a page of the same name and will never fire.",
                    'docs.yml',
                );
            }

            if ($from === $to) {
                $issues[] = ValidationIssue::error(
                    "Redirect `{$from}` points at itself.",
                    'docs.yml',
                );
            }
        }

        return $issues;
    }

    /** `slug` replaces the final segment only; it never moves a page. */
    private function resolvedReference(string $reference, FrontMatter $frontMatter): string
    {
        if ($frontMatter->slug === null) {
            return $reference;
        }

        $directory = str_contains($reference, '/')
            ? mb_substr($reference, 0, (int) mb_strrpos($reference, '/')).'/'
            : '';

        return $directory.mb_trim($frontMatter->slug, '/');
    }

    private function looksLikeAVersion(string $segment): bool
    {
        return preg_match('/^v?\d+(\.(\d+|x))*$/i', $segment) === 1;
    }
}
