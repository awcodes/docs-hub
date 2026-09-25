<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationPage;
use App\Models\ProjectVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentationPage>
 */
class DocumentationPageFactory extends Factory
{
    protected $model = DocumentationPage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);
        $slug = Str::slug($title);

        return [
            'project_version_id' => ProjectVersion::factory(),
            'source_path' => $slug.'.md',
            'slug' => $slug,
            'title' => Str::headline($title),
            'description' => fake()->sentence(),
            'headings' => [
                ['level' => 2, 'title' => 'Overview', 'anchor' => 'overview'],
                ['level' => 2, 'title' => 'Usage', 'anchor' => 'usage'],
            ],
            'content_hash' => fake()->sha256(),
            'source_updated_at' => now(),
        ];
    }
}
