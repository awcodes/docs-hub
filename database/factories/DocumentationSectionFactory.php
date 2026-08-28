<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationPage;
use App\Models\DocumentationSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationSection>
 */
class DocumentationSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $page = DocumentationPage::factory();
        $heading = fake()->sentence(3);

        return [
            'documentation_page_id' => $page,
            'documentation_snapshot_id' => fn (array $attributes): int => DocumentationPage::query()->findOrFail($attributes['documentation_page_id'])->documentation_snapshot_id,
            'heading_path' => [$heading],
            'heading' => $heading,
            'anchor' => str($heading)->slug()->toString(),
            'body' => fake()->paragraphs(2, true),
            'sort_order' => 0,
        ];
    }
}
