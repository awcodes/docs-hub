<?php

declare(strict_types=1);

use App\Enums\DocumentationType;
use App\Enums\VersioningMode;
use App\Enums\VersionStatus;
use App\Models\DocumentationPage;
use App\Models\Project;
use App\Models\ProjectVersion;

it('casts the registry columns that routing reads', function (): void {
    $project = Project::factory()->create();

    expect($project->versioning_mode)->toBe(VersioningMode::Versioned)
        ->and($project->is_visible)->toBeTrue();
});

it('resolves a project by slug rather than id', function (): void {
    expect(Project::factory()->create()->getRouteKeyName())->toBe('slug');
});

it('refuses two projects with the same slug', function (): void {
    Project::factory()->create(['slug' => 'example']);

    Project::factory()->create(['slug' => 'example']);
})->throws(Illuminate\Database\UniqueConstraintViolationException::class);

it('lists only visible projects for the homepage and the selector', function (): void {
    $listed = Project::factory()->create();
    $beingSetUp = Project::factory()->hidden()->create();

    $visible = Project::query()->visible()->pluck('id');

    expect($visible)->toContain($listed->id)
        ->and($visible)->not->toContain($beingSetUp->id);
});

it('finds the version that unversioned URLs resolve to', function (): void {
    $project = Project::factory()->create();
    ProjectVersion::factory()->for($project)->create(['version' => '0.x']);
    $current = ProjectVersion::factory()->for($project)->default()->create();

    expect($project->defaultVersion()->first()?->id)->toBe($current->id);
});

it('reports no default version rather than guessing one', function (): void {
    $project = Project::factory()->create();
    ProjectVersion::factory()->for($project)->create();

    expect($project->defaultVersion()->first())->toBeNull();
});

it('gives an application a rolling mode with no version segment', function (): void {
    $project = Project::factory()->application()->create();

    expect($project->versioning_mode)->toBe(VersioningMode::Rolling)
        ->and($project->versioning_mode->showsVersionSegment())->toBeFalse();
});

it('keeps one version per project per version string', function (): void {
    $project = Project::factory()->create();
    ProjectVersion::factory()->for($project)->create(['version' => '1.x']);

    ProjectVersion::factory()->for($project)->create(['version' => '1.x']);
})->throws(Illuminate\Database\UniqueConstraintViolationException::class);

it('lets two projects each have a 1.x', function (): void {
    ProjectVersion::factory()->create(['version' => '1.x']);
    ProjectVersion::factory()->create(['version' => '1.x']);

    expect(ProjectVersion::query()->where('version', '1.x')->count())->toBe(2);
});

it('registers a version as unsynchronized until a snapshot is published', function (): void {
    $version = ProjectVersion::factory()->create();

    expect($version->active_snapshot)->toBeNull()
        ->and($version->last_synced_at)->toBeNull()
        ->and($version->documentation_type)->toBe(DocumentationType::None)
        ->and($version->isPublished())->toBeFalse();

    $version = ProjectVersion::factory()->published()->create();

    expect($version->isPublished())->toBeTrue();
});

it('does not call a version published when it synchronized to nothing', function (): void {
    $version = ProjectVersion::factory()->published()->create([
        'documentation_type' => DocumentationType::None,
    ]);

    expect($version->isPublished())->toBeFalse();
});

it('keeps status and default selection separate', function (): void {
    $project = Project::factory()->create();

    $current = ProjectVersion::factory()->for($project)->default()->create([
        'version' => '1.x',
        'status' => VersionStatus::Current,
    ]);

    $beta = ProjectVersion::factory()->for($project)->create([
        'version' => '2.x',
        'status' => VersionStatus::Beta,
    ]);

    expect($beta->status->canBeDefault())->toBeFalse()
        ->and($beta->is_default)->toBeFalse()
        ->and($project->defaultVersion()->first()?->id)->toBe($current->id);
});

it('drops a version and its pages when the project goes', function (): void {
    $project = Project::factory()->create();
    $version = ProjectVersion::factory()->for($project)->create();
    DocumentationPage::factory()->for($version, 'version')->create();

    $project->delete();

    expect(ProjectVersion::query()->count())->toBe(0)
        ->and(DocumentationPage::query()->count())->toBe(0);
});

it('stores headings as structure rather than text', function (): void {
    $page = DocumentationPage::factory()->create([
        'headings' => [
            ['level' => 2, 'title' => 'One filename, three jobs', 'anchor' => 'one-filename-three-jobs'],
        ],
    ]);

    expect($page->refresh()->headings)->toBe([
        ['level' => 2, 'title' => 'One filename, three jobs', 'anchor' => 'one-filename-three-jobs'],
    ]);
});

it('keeps page slugs unique within a version but not across versions', function (): void {
    $project = Project::factory()->create();
    $one = ProjectVersion::factory()->for($project)->create(['version' => '1.x']);
    $two = ProjectVersion::factory()->for($project)->create(['version' => '2.x']);

    DocumentationPage::factory()->for($one, 'version')->create(['slug' => 'installation']);
    DocumentationPage::factory()->for($two, 'version')->create(['slug' => 'installation']);

    expect(DocumentationPage::query()->where('slug', 'installation')->count())->toBe(2);

    DocumentationPage::factory()->for($one, 'version')->create(['slug' => 'installation']);
})->throws(Illuminate\Database\UniqueConstraintViolationException::class);
