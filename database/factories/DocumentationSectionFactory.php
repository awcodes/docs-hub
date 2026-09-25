<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationPage;
use App\Models\DocumentationSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentationSection>
 */
class DocumentationSectionFactory extends Factory
{
    protected $model = DocumentationSection::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $heading = fake()->unique()->words(3, true);

        $page = DocumentationPage::factory();

        return [
            'documentation_page_id' => $page,
            'project_version_id' => fn (array $attributes): int => DocumentationPage::query()
                ->findOrFail($attributes['documentation_page_id'])
                ->project_version_id,
            'heading' => Str::ucfirst($heading),
            'anchor' => Str::slug($heading),
            'level' => 2,
            'position' => 1,
            'content' => fake()->paragraph(),
        ];
    }

    /** The text before a page's first heading, which has nothing to link to. */
    public function lead(): self
    {
        return $this->state(fn (): array => [
            'heading' => null,
            'anchor' => null,
            'level' => 0,
            'position' => 0,
        ]);
    }
}
