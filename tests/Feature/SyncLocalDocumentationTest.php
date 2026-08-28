<?php

declare(strict_types=1);

use App\Documentation\Actions\SyncLocalDocumentation;
use App\Documentation\Exceptions\DocumentationValidationException;
use App\Enums\DocumentationSnapshotState;
use App\Enums\DocumentationType;
use App\Models\DocumentationSnapshot;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;

it('builds and publishes immutable structured documentation', function (): void {
    Storage::fake('local');
    $package = Package::factory()->create(['slug' => 'curator']);
    $version = PackageVersion::factory()->for($package)->create(['version' => '5.x']);
    $commit = str_repeat('a', 40);

    $published = app(SyncLocalDocumentation::class)->handle(
        $version,
        base_path('tests/Fixtures/documentation/structured'),
        $commit,
    );

    $snapshot = $published->activeSnapshot;

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->state)->toBe(DocumentationSnapshotState::Active)
        ->and($snapshot->documentation_type)->toBe(DocumentationType::Structured)
        ->and($snapshot->pages)->toHaveCount(4)
        ->and($snapshot->sections)->toHaveCount(4)
        ->and($snapshot->navigationItems)->toHaveCount(4)
        ->and($snapshot->redirects)->toHaveCount(1)
        ->and($snapshot->pages()->where('route_path', 'install')->exists())->toBeTrue()
        ->and($snapshot->pages()->where('route_path', 'unlisted')->firstOrFail()->is_listed)->toBeFalse();

    Storage::disk('local')->assertExists([
        "{$snapshot->storage_prefix}/docs.yml",
        "{$snapshot->storage_prefix}/index.md",
        "{$snapshot->storage_prefix}/concepts/media.md",
    ]);
});

it('publishes README fallback at the canonical version root', function (): void {
    Storage::fake('local');
    $version = PackageVersion::factory()->create();

    $published = app(SyncLocalDocumentation::class)->handle(
        $version,
        base_path('tests/Fixtures/documentation/readme'),
        str_repeat('b', 40),
    );

    $snapshot = $published->activeSnapshot;
    $page = $snapshot->pages()->sole();

    expect($snapshot->documentation_type)->toBe(DocumentationType::Readme)
        ->and($page->source_path)->toBe('README.md')
        ->and($page->route_path)->toBe('')
        ->and($page->is_listed)->toBeTrue();

    Storage::disk('local')->assertExists("{$snapshot->storage_prefix}/README.md");
});

it('publishes an explicit no-documentation snapshot', function (): void {
    Storage::fake('local');
    $version = PackageVersion::factory()->create();

    $published = app(SyncLocalDocumentation::class)->handle(
        $version,
        base_path('tests/Fixtures/documentation/none'),
        str_repeat('c', 40),
    );

    expect($published->documentation_type)->toBe(DocumentationType::None)
        ->and($published->activeSnapshot->pages)->toBeEmpty();
});

it('does not rebuild an already active commit', function (): void {
    Storage::fake('local');
    $version = PackageVersion::factory()->create();
    $commit = str_repeat('d', 40);
    $synchronizer = app(SyncLocalDocumentation::class);

    $synchronizer->handle($version, base_path('tests/Fixtures/documentation/readme'), $commit);
    $synchronizer->handle($version, base_path('tests/Fixtures/documentation/readme'), $commit);

    expect($version->snapshots()->count())->toBe(1);
});

it('preserves the active snapshot when validation fails', function (): void {
    Storage::fake('local');
    $version = PackageVersion::factory()->create();
    $activeSnapshot = DocumentationSnapshot::factory()->for($version)->active()->create();
    $version->update([
        'active_snapshot_id' => $activeSnapshot->getKey(),
        'source_commit' => $activeSnapshot->source_commit,
        'documentation_type' => $activeSnapshot->documentation_type,
    ]);

    expect(fn () => app(SyncLocalDocumentation::class)->handle(
        $version,
        base_path('tests/Fixtures/documentation/missing-navigation'),
        str_repeat('e', 40),
    ))->toThrow(DocumentationValidationException::class)
        ->and($version->refresh()->active_snapshot_id)->toBe($activeSnapshot->getKey())
        ->and($version->source_commit)->toBe($activeSnapshot->source_commit)
        ->and($version->last_sync_error)->not->toBeNull()
        ->and($activeSnapshot->refresh()->state)->toBe(DocumentationSnapshotState::Active);
});

it('synchronizes a registered package through the local command', function (): void {
    Storage::fake('local');
    $package = Package::factory()->create(['slug' => 'curator']);
    PackageVersion::factory()->for($package)->create(['version' => '5.x']);
    $commit = str_repeat('f', 40);

    $this->artisan('docs:sync-local', [
        'package' => 'curator',
        'version' => '5.x',
        'checkout' => base_path('tests/Fixtures/documentation/structured'),
        '--commit' => $commit,
    ])->expectsOutputToContain("Published [curator 5.x] at commit [{$commit}].")
        ->assertSuccessful();
});

it('fails the local command for an unknown package', function (): void {
    $this->artisan('docs:sync-local', [
        'package' => 'missing',
        'version' => '1.x',
        'checkout' => base_path('tests/Fixtures/documentation/none'),
        '--commit' => str_repeat('a', 40),
    ])->expectsOutputToContain('Package [missing] is not registered.')
        ->assertFailed();
});
