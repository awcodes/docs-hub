<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProjectKind;
use App\Enums\VersioningMode;
use App\Enums\VersionStatus;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * The registry a fresh deployment starts from.
 *
 * Only the registry is seeded — what a person would otherwise enter in the
 * panel. Everything synchronization owns (snapshots, commits, navigation) is
 * left for `docs:sync`, which is run after seeding and fills it in from GitHub.
 *
 * Safe to run again: projects are matched on slug and versions on their
 * project and name, so a rerun updates rather than duplicates.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->projects() as $definition) {
            $project = Project::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'kind' => ProjectKind::Package,
                    'repository' => $definition['repository'],
                    'docs_path' => 'docs',
                    'default_branch' => $definition['branch'],
                    'description' => $definition['description'],
                    'group' => $definition['group'],
                    'versioning_mode' => $definition['versioning_mode'] ?? VersioningMode::Versioned,
                    'is_visible' => true,
                ],
            );

            $project->versions()->updateOrCreate(
                ['version' => $definition['version'] ?? $definition['branch']],
                [
                    'git_ref' => $definition['branch'],
                    'status' => VersionStatus::Current,
                    'is_default' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{slug: string, name: string, repository: string, branch: string, description: string, group: string, version?: string, versioning_mode?: VersioningMode}>
     */
    private function projects(): array
    {
        return [
            [
                'slug' => 'badgeable-column',
                'name' => 'Badgeable Column',
                'repository' => 'awcodes/filament-badgeable-column',
                'branch' => '4.x',
                'description' => 'Filament Tables column to append and prepend badges.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'botly',
                'name' => 'Botly',
                'repository' => 'awcodes/botly',
                'branch' => '1.x',
                'description' => "Botly is a Filament plugin to manage your site's robots.txt file directly from a Filament admin panel.",
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'content-faker',
                'name' => 'Content Faker',
                'repository' => 'awcodes/content-faker',
                'branch' => '1.x',
                'description' => 'Generate fake Markdown, HTML, and rich editor content for Laravel factories, seeders, tests, and previews.',
                'group' => 'PHP Packages',
            ],
            [
                'slug' => 'curator',
                'name' => 'Curator',
                'repository' => 'awcodes/filament-curator',
                'branch' => '5.x',
                'description' => 'A media picker plugin for FilamentPHP.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'gravatar',
                'name' => 'Gravatar',
                'repository' => 'awcodes/filament-gravatar',
                'branch' => '4.x',
                'description' => "Replace Filament's default avatar url provider with one for Gravatar.",
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'gtm',
                'name' => 'Google Tag Manager',
                'repository' => 'awcodes/gtm',
                'branch' => '2.x',
                'description' => 'Easy integration of Google Tag Manager into your Laravel application.',
                'group' => 'Laravel Packages',
            ],
            [
                'slug' => 'light-switch',
                'name' => 'Light Switch',
                'repository' => 'awcodes/light-switch',
                'branch' => '3.x',
                'description' => 'Plugin to add theme switching (light/dark/system) to the auth pages for Filament Panels',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'mason',
                'name' => 'Mason',
                'repository' => 'awcodes/mason',
                'branch' => '3.x',
                'description' => 'A simple block based drag and drop page / document builder field for Filament.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'matinee',
                'name' => 'Matinée',
                'repository' => 'awcodes/Matinee',
                'branch' => '3.x',
                'description' => 'OEmbed field for Filament Panel and Form Builders.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'overlook',
                'name' => 'Overlook',
                'repository' => 'awcodes/overlook',
                'branch' => '4.x',
                'description' => 'A Filament plugin that adds an app overview widget to your admin panel.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'palette',
                'name' => 'Palette',
                'repository' => 'awcodes/palette',
                'branch' => '3.x',
                'description' => 'A color picker field for Filament Forms that uses preset color palettes.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'postal-codes',
                'name' => 'Postal Codes',
                'repository' => 'awcodes/postal-codes',
                'branch' => '1.x',
                'description' => 'This is a package to easily install and use postal codes in your Laravel application from GeoNames.org',
                'group' => 'Laravel Packages',
            ],
            [
                'slug' => 'quick-create',
                'name' => 'Quick Create',
                'repository' => 'awcodes/filament-quick-create',
                'branch' => '5.x',
                'description' => 'Plugin for Filament Admin that adds a dropdown menu to the header to quickly create new items.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'recently',
                'name' => 'Recently',
                'repository' => 'awcodes/recently',
                'branch' => '3.x',
                'description' => 'Easily track and access recently viewed records in your filament panels.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'richer-editor',
                'name' => 'Richer Editor',
                'repository' => 'awcodes/richer-editor',
                'branch' => '2.x',
                'description' => 'A collection of extensions and tools to enhance the Filament Rich Editor field.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'shout',
                'name' => 'Shout',
                'repository' => 'awcodes/shout',
                'branch' => '4.x',
                'description' => 'A simple inline contextual notice for Filament forms, basically just a fancy placeholder.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'sticky-header',
                'name' => 'Sticky Header',
                'repository' => 'awcodes/filament-sticky-header',
                'branch' => '4.x',
                'description' => 'A Filament Panel plugin to make page headers sticky when scrolling.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'typebar',
                'name' => 'Typebar',
                'repository' => 'awcodes/typebar',
                'branch' => 'main',
                'description' => 'Mobile Markdown symbol row for the Filament Markdown editor.',
                'group' => 'Filament Plugins',
                'versioning_mode' => VersioningMode::Rolling,
            ],
            [
                'slug' => 'versions',
                'name' => 'Versions',
                'repository' => 'awcodes/filament-versions',
                'branch' => '4.x',
                'description' => 'A mostly useless package to display framework versions at the bottom of the navigation panel.',
                'group' => 'Filament Plugins',
            ],
            [
                'slug' => 'focus',
                'name' => 'Focus',
                'repository' => 'awcodes/focus',
                'branch' => 'main',
                'description' => 'Generate consistent documentation screenshots from Laravel Workbench applications using Playwright.',
                'group' => 'Tools',
                'version' => '0.x',
            ],
        ];
    }
}
