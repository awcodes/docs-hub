<?php

declare(strict_types=1);

use App\Enums\VersioningMode;
use App\Enums\VersionStatus;
use App\Models\Project;
use App\Models\ProjectVersion;

/*
| The root is a project directory, not an article about the hub.
*/

it('lists projects to a guest', function (): void {
    Project::factory()->create(['name' => 'Curator']);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Curator');
});

it('lists visible projects in their groups', function (): void {
    Project::factory()->create(['name' => 'Example Package', 'group' => 'Packages']);
    Project::factory()->create(['name' => 'Changelog', 'group' => 'Applications']);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Example Package')
        ->assertSee('Changelog')
        ->assertSeeInOrder(['Packages', 'Applications']);
});

it('puts packages before applications, against alphabetical order', function (): void {
    // The packages are what the applications are built on, so the order is
    // named rather than sorted.
    Project::factory()->create(['name' => 'Changelog', 'group' => 'Applications']);
    Project::factory()->create(['name' => 'Example Package', 'group' => 'Packages']);
    Project::factory()->create(['name' => 'Some Tool', 'group' => 'Tools']);

    $this->get('/')
        ->assertSeeInOrder(['Packages', 'Applications', 'Tools']);
});

it('keeps a hidden project off the directory', function (): void {
    Project::factory()->create(['name' => 'Half Built', 'is_visible' => false]);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('Half Built');
});

it('shows the current version of a versioned project', function (): void {
    $project = Project::factory()->create([
        'name' => 'Example Package',
        'versioning_mode' => VersioningMode::Versioned,
    ]);

    ProjectVersion::factory()->for($project)->create([
        'version' => '1.x',
        'status' => VersionStatus::Current,
        'active_snapshot' => 'abc123',
    ]);

    ProjectVersion::factory()->for($project)->create([
        'version' => '0.x',
        'status' => VersionStatus::Legacy,
        'active_snapshot' => 'def456',
    ]);

    $this->get('/')
        ->assertSee('Current: 1.x')
        ->assertDontSee('Current: 0.x');
});

it('shows no version line for a rolling project', function (): void {
    // The absence is the distinction between the two kinds.
    $project = Project::factory()->create([
        'name' => 'Docs Hub',
        'versioning_mode' => VersioningMode::Rolling,
    ]);

    ProjectVersion::factory()->for($project)->create([
        'version' => 'main',
        'status' => VersionStatus::Current,
        'active_snapshot' => 'abc123',
    ]);

    $this->get('/')
        ->assertSee('Docs Hub')
        ->assertDontSee('Current:');
});

it('says so when nothing is registered', function (): void {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Nothing is registered yet');
});

it('renders no table of contents column', function (): void {
    // It is a fixed-width column, so rendering it empty leaves the directory
    // centred on space it does not occupy.
    Project::factory()->create();

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('Table of contents');
});

it('loads analytics in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('cdn.usefathom.com/script.js', escape: false);
});

it('leaves analytics out outside production', function (): void {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('cdn.usefathom.com', escape: false);
});
