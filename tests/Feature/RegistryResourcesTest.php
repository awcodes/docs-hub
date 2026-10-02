<?php

declare(strict_types=1);

use App\Enums\ProjectKind;
use App\Enums\VersioningMode;
use App\Enums\VersionStatus;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\ProjectVersions\Pages\CreateProjectVersion;
use App\Filament\Resources\ProjectVersions\Pages\EditProjectVersion;
use App\Filament\Resources\ProjectVersions\Pages\ListProjectVersions;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;

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

/*
| The same versions, managed from the project they belong to. The project is
| the owner record there, so the form never asks for it.
*/

function versionsOf(Project $project): Livewire\Features\SupportTesting\Testable
{
    return livewire(VersionsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

it('shows only the project\'s own versions on its edit page', function (): void {
    $project = Project::factory()->create();
    $mine = ProjectVersion::factory()->for($project)->create(['version' => '1.x']);
    $theirs = ProjectVersion::factory()->for(Project::factory())->create(['version' => '1.x']);

    versionsOf($project)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->assertTableColumnHidden('project.name');
});

it('creates a version for the project from its edit page', function (): void {
    $project = Project::factory()->create();
    $old = ProjectVersion::factory()->for($project)->default()->create(['version' => '1.x']);

    versionsOf($project)
        ->callTableAction(CreateAction::class, data: [
            'version' => '2.x',
            'git_ref' => '2.x',
            'status' => VersionStatus::Current->value,
            'is_default' => true,
        ])
        ->assertHasNoTableActionErrors();

    $new = $project->versions()->where('version', '2.x')->sole();

    expect($new->is_default)->toBeTrue()
        ->and($old->refresh()->is_default)->toBeFalse();
});

it('refuses a version name the project already has', function (): void {
    $project = Project::factory()->create();
    ProjectVersion::factory()->for($project)->create(['version' => '1.x']);

    // Another project's `2.x` must not count against this one.
    ProjectVersion::factory()->for(Project::factory())->create(['version' => '2.x']);

    versionsOf($project)
        ->callTableAction(CreateAction::class, data: [
            'version' => '1.x',
            'git_ref' => '1.x',
            'status' => VersionStatus::Current->value,
        ])
        ->assertHasTableActionErrors(['version' => 'unique']);

    versionsOf($project)
        ->callTableAction(CreateAction::class, data: [
            'version' => '2.x',
            'git_ref' => '2.x',
            'status' => VersionStatus::Current->value,
        ])
        ->assertHasNoTableActionErrors();
});

it('leaves one default version when one is made default from the project', function (): void {
    $project = Project::factory()->create();
    $first = ProjectVersion::factory()->for($project)->default()->create(['version' => '1.x']);
    $second = ProjectVersion::factory()->for($project)->create(['version' => '2.x']);

    versionsOf($project)
        ->callTableAction(EditAction::class, $second, data: ['is_default' => true])
        ->assertHasNoTableActionErrors();

    expect($second->refresh()->is_default)->toBeTrue()
        ->and($first->refresh()->is_default)->toBeFalse();
});
