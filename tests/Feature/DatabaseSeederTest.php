<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

it('creates a local admin account and no projects', function (): void {
    $this->artisan('db:seed')->assertSuccessful();

    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue()
        ->and(Project::query()->count())->toBe(0);
});

it('creates no default account in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and(Project::query()->count())->toBe(0);
});
