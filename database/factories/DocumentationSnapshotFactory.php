<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentationSnapshotState;
use App\Enums\DocumentationType;
use App\Models\DocumentationSnapshot;
use App\Models\PackageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationSnapshot>
 */
class DocumentationSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $commit = fake()->sha1();

        return [
            'package_version_id' => PackageVersion::factory(),
            'source_commit' => $commit,
            'storage_disk' => 'local',
            'storage_prefix' => 'docs/'.fake()->uuid().'/'.$commit,
            'documentation_type' => DocumentationType::Structured,
            'state' => DocumentationSnapshotState::Building,
            'published_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => ['state' => DocumentationSnapshotState::Ready]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'state' => DocumentationSnapshotState::Active,
            'published_at' => now(),
        ]);
    }
}
