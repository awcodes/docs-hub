<?php

declare(strict_types=1);

use App\Enums\ProjectKind;
use App\Enums\VersioningMode;
use App\Enums\VersionStatus;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\ProjectVersions\Pages\CreateProjectVersion;
use App\Filament\Resources\ProjectVersions\Pages\EditProjectVersion;
use App\Filament\Resources\ProjectVersions\Pages\ListProjectVersions;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\User;

use function Pest\Livewire\livewire;

/*
| The registry is how a repository joins the hub. Until these existed it was
| reachable only from tinker, which is not a thing anyone should have to
| reconstruct to register a project.
|
| Verification is rendering the page and driving the form, not asserting that a
| column was written down.
*/

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('lists projects for an admin', function (): void {
    $project = Project::factory()->create(['name' => 'Example Package']);

    // Split rather than chained: `assertSuccessful()` hands back a
    // TestResponse, which has none of the table assertions.
    $page = livewire(ListProjects::class);

    $page->assertSuccessful();
    $page->assertCanSeeTableRecords([$project]);
});

it('registers a project through the form', function (): void {
    livewire(CreateProject::class)
        ->fillForm([
            'slug' => 'widgets',
            'name' => 'Widgets',
            'kind' => ProjectKind::Package->value,
            'group' => 'Packages',
            'repository' => 'acme/widgets',
            'docs_path' => 'docs',
            'default_branch' => 'main',
            'versioning_mode' => VersioningMode::Versioned->value,
            'is_visible' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Project::where('slug', 'widgets')->exists())->toBeTrue();
});

it('refuses a slug that is a reserved root path', function (): void {
    // Documentation owns the root of the site, so `admin` would register fine
    // and then never resolve — the router reads that segment first.
    livewire(CreateProject::class)
        ->fillForm([
            'slug' => 'admin',
            'name' => 'Administration',
            'kind' => ProjectKind::Application->value,
            'group' => 'Applications',
            'repository' => 'acme/administration',
            'docs_path' => 'docs',
            'default_branch' => 'main',
            'versioning_mode' => VersioningMode::Rolling->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    expect(Project::where('slug', 'admin')->exists())->toBeFalse();
});

it('lists versions for an admin', function (): void {
    $version = ProjectVersion::factory()->for(Project::factory())->create();

    // Split rather than chained: `assertSuccessful()` hands back a
    // TestResponse, which has none of the table assertions.
    $page = livewire(ListProjectVersions::class);

    $page->assertSuccessful();
    $page->assertCanSeeTableRecords([$version]);
});

it('leaves one default version when a second is made default', function (): void {
    $project = Project::factory()->create();
    $first = ProjectVersion::factory()->for($project)->default()->create(['version' => '1.x']);
    $second = ProjectVersion::factory()->for($project)->create(['version' => '2.x']);

    livewire(EditProjectVersion::class, ['record' => $second->getKey()])
        ->fillForm(['is_default' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($second->refresh()->is_default)->toBeTrue()
        ->and($first->refresh()->is_default)->toBeFalse();
});

it('does not clear a default belonging to another project', function (): void {
    $mine = ProjectVersion::factory()->for(Project::factory())->default()->create();
    $theirs = ProjectVersion::factory()->for(Project::factory())->default()->create();

    livewire(CreateProjectVersion::class)
        ->fillForm([
            'project_id' => $mine->project_id,
            'version' => '2.x',
            'git_ref' => '2.x',
            'status' => VersionStatus::Current->value,
            'is_default' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($mine->refresh()->is_default)->toBeFalse()
        ->and($theirs->refresh()->is_default)->toBeTrue();
});

it('does not let the form overwrite what synchronization wrote', function (): void {
    // These describe a published snapshot. A form that round-tripped them
    // would let an edit describe a snapshot that does not exist.
    $version = ProjectVersion::factory()->for(Project::factory())->create([
        'source_commit' => 'abc123',
        'active_snapshot' => 'abc123',
    ]);

    livewire(EditProjectVersion::class, ['record' => $version->getKey()])
        ->fillForm(['status' => VersionStatus::Legacy->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($version->refresh())
        ->status->toBe(VersionStatus::Legacy)
        ->source_commit->toBe('abc123')
        ->active_snapshot->toBe('abc123');
});
