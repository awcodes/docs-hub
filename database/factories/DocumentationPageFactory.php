<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationPage;
use App\Models\DocumentationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationPage>
 */
class DocumentationPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $routePath = fake()->unique()->slug(2);

        return [
            'documentation_snapshot_id' => DocumentationSnapshot::factory(),
            'source_path' => $routePath.'.md',
            'slug' => null,
            'route_path' => $routePath,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'headings' => [],
            'content_hash' => hash('sha256', fake()->text()),
            'is_listed' => false,
            'navigation_order' => null,
            'source_updated_at' => null,
        ];
    }
}
