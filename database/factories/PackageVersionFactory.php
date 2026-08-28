<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PackageVersionStatus;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageVersion>
 */
class PackageVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'version' => fake()->unique()->numberBetween(1, 20).'.x',
            'git_ref' => fn (array $attributes): string => $attributes['version'],
            'status' => PackageVersionStatus::Supported,
            'is_default' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (): array => ['status' => PackageVersionStatus::Current]);
    }

    public function default(): static
    {
        return $this->current()->state(fn (): array => ['is_default' => true]);
    }
}
