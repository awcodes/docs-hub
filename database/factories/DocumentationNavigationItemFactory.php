<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationNavigationItem;
use App\Models\DocumentationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationNavigationItem>
 */
class DocumentationNavigationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'documentation_snapshot_id' => DocumentationSnapshot::factory(),
            'parent_id' => null,
            'title' => fake()->sentence(2),
            'route_path' => fake()->unique()->slug(2),
            'depth' => 0,
            'sort_order' => 0,
            'position_path' => fake()->unique()->numerify('####'),
        ];
    }
}
