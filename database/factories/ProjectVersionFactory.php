<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentationType;
use App\Enums\VersionStatus;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectVersion>
 */
class ProjectVersionFactory extends Factory
{
    protected $model = ProjectVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'version' => '1.x',
            'git_ref' => '1.x',
            'status' => VersionStatus::Current,
            'is_default' => false,

            // Registered but never synchronized — the state a version is in
            // between someone adding it and the first sync succeeding.
            'last_synced_at' => null,
            'source_commit' => null,
            'active_snapshot' => null,
            'documentation_type' => DocumentationType::None,
        ];
    }

    public function default(): self
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    /** A version that has published a snapshot readers can open. */
    public function published(): self
    {
        return $this->state(fn (): array => [
            'last_synced_at' => now(),
            'source_commit' => fake()->sha1(),
            'active_snapshot' => fake()->sha1(),
            'documentation_type' => DocumentationType::Structured,
        ]);
    }

    public function status(VersionStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
