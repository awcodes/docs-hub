<?php

declare(strict_types=1);

use App\Enums\VersioningMode;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\User;
use Database\Seeders\ProjectSeeder;

it('registers every project with a default version ready to sync', function (): void {
    $this->seed(ProjectSeeder::class);

    expect(Project::query()->count())->toBe(20)
        ->and(ProjectVersion::query()->count())->toBe(20)
        ->and(Project::query()->doesntHave('defaultVersion')->exists())->toBeFalse()
        ->and(ProjectVersion::query()->whereNotNull('active_snapshot')->exists())->toBeFalse();
});

it('keeps a version name apart from the branch it reads', function (): void {
    $this->seed(ProjectSeeder::class);

    $focus = Project::query()->where('slug', 'focus')->firstOrFail()->defaultVersion;

    expect($focus?->version)->toBe('0.x')
        ->and($focus?->git_ref)->toBe('main')
        ->and(Project::query()->where('slug', 'typebar')->firstOrFail()->versioning_mode)->toBe(VersioningMode::Rolling);
});

it('can run again without duplicating the registry', function (): void {
    $this->seed(ProjectSeeder::class);
    $this->seed(ProjectSeeder::class);

    expect(Project::query()->count())->toBe(20)
        ->and(ProjectVersion::query()->count())->toBe(20);
});

it('seeds the registry but no default account in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(Project::query()->count())->toBe(20)
        ->and(User::query()->count())->toBe(0);
});
