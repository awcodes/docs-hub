<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

/*
| Reading needs no account; the panel is the one place that does. Every account
| is an administrator, because readers never sign in.
*/

it('sends a guest to the panel login', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('renders the login page with the application name', function (): void {
    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee(config('app.name'));
});

it('lets a signed-in user into the panel', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertSuccessful();
});

it('lets a signed-in user into the registry', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin/projects')
        ->assertSuccessful();
});

it('gives the panel a link back to the documentation', function (): void {
    $item = collect(Filament::getPanel('admin')->getNavigationItems())
        ->first(fn (NavigationItem $item): bool => $item->getLabel() === 'View Site');

    expect($item)->not->toBeNull()
        ->and($item?->getUrl())->toBe(route('documentation.home'));
});
