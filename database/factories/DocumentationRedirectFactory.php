<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationRedirect;
use App\Models\DocumentationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationRedirect>
 */
class DocumentationRedirectFactory extends Factory
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
            'source_route' => fake()->unique()->slug(2),
            'destination_route' => fake()->unique()->slug(2),
        ];
    }
}
