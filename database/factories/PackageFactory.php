<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => str($name)->slug()->toString(),
            'name' => $name,
            'repository' => 'awcodes/'.str($name)->slug(),
            'docs_path' => 'docs',
            'description' => fake()->sentence(),
            'group' => fake()->randomElement(['Filament', 'Laravel', 'PHP']),
            'is_visible' => true,
        ];
    }
}
