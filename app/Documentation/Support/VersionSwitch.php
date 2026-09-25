<?php

declare(strict_types=1);

namespace App\Documentation\Support;

use App\Enums\VersionStatus;
use App\Models\DocumentationPage;
use App\Models\Project;
use App\Models\ProjectVersion;

/**
 * Where each of a project's versions would take the reader from here.
 *
 * Worked out centrally, because switching version is not a URL substitution:
 * `/example/1.x/architecture/boundaries` has no
 * equivalent on 2.x if that page was never written there, and the reader should
 * land on the 2.x root rather than a 404.
 *
 * Resolved when the link is built rather than when it is followed, so the
 * selector shows where each option actually goes and no redirect hop is needed.
 */
final readonly class VersionSwitch
{
    public function __construct(
        private DocumentationUrl $urls = new DocumentationUrl,
    ) {}

    /**
     * Every version of this project, newest first, with where it leads.
     *
     * Ordered by version rather than by status, naturally so that `10.x` sorts
     * above `2.x`. That is the order a selector shows, and it needs no ranking
     * the registry does not have — the status label beside each one is what
     * says which is current.
     *
     * @return list<array{version: string, status: VersionStatus, url: string, current: bool, equivalent: bool}>
     */
    public function options(ProjectVersion $current, ?DocumentationPage $page): array
    {
        $project = $current->project;

        /** @var list<ProjectVersion> $versions */
        $versions = $project->versions()->get()->all();

        usort($versions, static fn (ProjectVersion $a, ProjectVersion $b): int => strnatcasecmp($b->version, $a->version));

        return array_map(
            fn (ProjectVersion $version): array => $this->option($project, $version, $current, $page),
            $versions,
        );
    }

    /**
     * The version this project currently recommends, if it is not this one.
     *
     * What the legacy notice points at. `status` rather than
     * `is_default`, because the two are deliberately separate concerns and it
     * is the support policy a reader on old documentation needs to know about.
     *
     * @return array{version: string, url: string}|null
     */
    public function currentVersion(ProjectVersion $viewing, ?DocumentationPage $page): ?array
    {
        if ($viewing->status === VersionStatus::Current) {
            return null;
        }

        $current = $viewing->project->versions()
            ->where('status', VersionStatus::Current->value)
            ->first();

        if (! $current instanceof ProjectVersion) {
            return null;
        }

        $option = $this->option($viewing->project, $current, $viewing, $page);

        return ['version' => $current->version, 'url' => $option['url']];
    }

    /**
     * @return array{version: string, status: VersionStatus, url: string, current: bool, equivalent: bool}
     */
    private function option(
        Project $project,
        ProjectVersion $version,
        ProjectVersion $current,
        ?DocumentationPage $page,
    ): array {
        $equivalent = $page instanceof DocumentationPage
            && $version->pages()->where('slug', $page->slug)->exists();

        return [
            'version' => $version->version,
            'status' => $version->status,
            // Falling back to the version root loses the reader's place, which
            // is the right default but still a loss — the manifest's redirect
            // map is how a repository recovers it for a page it renamed.
            'url' => $equivalent
                ? $this->urls->page($project, $version, $page->slug)
                : $this->urls->page($project, $version),
            'current' => $version->is($current),
            'equivalent' => $equivalent,
        ];
    }
}
