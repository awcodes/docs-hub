<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectKind;
use App\Enums\VersioningMode;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'slug' => $slug,
            'name' => Str::headline($name),
            'kind' => ProjectKind::Package,
            'repository' => 'acme/'.$slug,
            'docs_path' => 'docs',
            'default_branch' => '1.x',
            'description' => fake()->sentence(),
            'group' => 'Packages',
            'versioning_mode' => VersioningMode::Versioned,
            'is_visible' => true,
        ];
    }

    /**
     * An application: one deployed instance, one documentation set, no version
     * segment in its URLs.
     */
    public function application(): self
    {
        return $this->state(fn (): array => [
            'kind' => ProjectKind::Application,
            'group' => 'Applications',
            'versioning_mode' => VersioningMode::Rolling,
            'default_branch' => 'main',
        ]);
    }

    public function hidden(): self
    {
        return $this->state(fn (): array => ['is_visible' => false]);
    }
}
