<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VersioningMode;
use App\Enums\VersionStatus;
use App\Models\Project;
use Illuminate\Contracts\View\View;

/**
 * The documentation root: a directory of projects, not an article.
 *
 * The hub aggregates documentation that is owned elsewhere, so the thing a
 * reader wants at `/` is the list of what is here — rows they can scan, in the
 * groups the registry gives them, rather than a welcome page about the hub.
 */
final class ProjectController extends Controller
{
    /**
     * The order the homepage's groups appear in.
     *
     * Named rather than sorted, because alphabetical puts Applications above
     * Packages and the packages are what the applications are built on.
     * Anything the registry invents beyond these follows, in
     * alphabetical order, rather than being dropped.
     *
     * @var list<string>
     */
    private const array GROUP_ORDER = ['Packages', 'Applications', 'Tools'];

    public function __invoke(): View
    {
        return view('documentation.home', [
            'groups' => $this->groups(),
        ]);
    }

    /**
     * Visible projects, grouped and ordered for reading.
     *
     * @return array<string, list<array{name: string, slug: string, description: string|null, version: string|null}>>
     */
    private function groups(): array
    {
        return Project::query()
            ->visible()
            ->with(['versions' => fn ($query) => $query->whereNotNull('active_snapshot')])
            ->orderBy('name')
            ->get()
            ->groupBy('group')
            ->sortBy(fn ($projects, string $group): array => [
                // Unknown groups sort after the named ones, then by name.
                in_array($group, self::GROUP_ORDER, true) ? 0 : 1,
                array_search($group, self::GROUP_ORDER, true) ?: 0,
                $group,
            ])
            ->map(fn ($projects) => $projects->map(fn (Project $project): array => [
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'version' => $this->currentVersion($project),
            ])->values()->all())
            ->all();
    }

    /**
     * The version to advertise, or null for a rolling project.
     *
     * A rolling project showing no version line is the whole visual distinction
     * between the two kinds, rather than a badge. Read from `status` rather than `is_default`: which version
     * is current is a support-policy answer, and `is_default` only says where
     * an unversioned URL lands.
     */
    private function currentVersion(Project $project): ?string
    {
        if ($project->versioning_mode === VersioningMode::Rolling) {
            return null;
        }

        return $project->versions
            ->firstWhere('status', VersionStatus::Current)
            ?->version;
    }
}
