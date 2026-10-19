<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Documentation\Actions\RenderProjectOverview;
use App\Documentation\Support\DocumentationUrl;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What another site needs to present one project's documentation.
 *
 * Every URL is absolute, because none of them will be followed from the hub.
 * Only published versions are listed: a registered version with no snapshot
 * is a 404 to a reader, and offering a link to one is worse than leaving it
 * out.
 *
 * @mixin Project
 */
final class RepositoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;
        $default = $project->defaultVersion;

        $versions = $project->versions()->get()
            ->filter(fn (ProjectVersion $version): bool => $version->isPublished())
            ->sort(fn (ProjectVersion $a, ProjectVersion $b): int => strnatcasecmp($b->version, $a->version))
            ->values();

        return [
            'slug' => $project->slug,
            'name' => $project->name,
            'repository' => $project->repository,
            'versioning_mode' => $project->versioning_mode->value,
            'docs_url' => $default instanceof ProjectVersion ? $this->url($project, $default) : null,
            'default_version' => $default instanceof ProjectVersion ? [
                'version' => $default->version,
                'git_ref' => $default->git_ref,
                'status' => $default->status->value,
                'source_commit' => $default->source_commit,
                'last_synced_at' => $default->last_synced_at?->toIso8601String(),
            ] : null,
            'versions' => $versions->map(fn (ProjectVersion $version): array => [
                'version' => $version->version,
                'status' => $version->status->value,
                'is_default' => $version->is_default,
                'url' => $this->url($project, $version),
            ])->all(),
            'index' => (new RenderProjectOverview)->handle($project),
        ];
    }

    private function url(Project $project, ProjectVersion $version): string
    {
        return mb_rtrim((string) config('app.url'), '/').(new DocumentationUrl)->page($project, $version);
    }
}
