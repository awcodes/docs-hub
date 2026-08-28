<?php

declare(strict_types=1);

use App\Documentation\Actions\PublishSnapshot;
use App\Enums\DocumentationSnapshotState;
use App\Enums\DocumentationType;
use App\Enums\PackageVersionStatus;
use App\Models\DocumentationNavigationItem;
use App\Models\DocumentationPage;
use App\Models\DocumentationRedirect;
use App\Models\DocumentationSection;
use App\Models\DocumentationSnapshot;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Database\QueryException;

it('casts package version and snapshot state', function (): void {
    $version = PackageVersion::factory()->default()->create();
    $snapshot = DocumentationSnapshot::factory()
        ->for($version)
        ->ready()
        ->create(['documentation_type' => DocumentationType::Readme]);

    expect($version->status)->toBe(PackageVersionStatus::Current)
        ->and($version->is_default)->toBeTrue()
        ->and($snapshot->state)->toBe(DocumentationSnapshotState::Ready)
        ->and($snapshot->documentation_type)->toBe(DocumentationType::Readme);
});

it('keeps snapshot routes unique without making final slugs globally unique', function (): void {
    $snapshot = DocumentationSnapshot::factory()->create();

    DocumentationPage::factory()->for($snapshot, 'snapshot')->create([
        'source_path' => 'concepts/index.md',
        'slug' => 'index',
        'route_path' => 'concepts/index',
    ]);

    DocumentationPage::factory()->for($snapshot, 'snapshot')->create([
        'source_path' => 'components/index.md',
        'slug' => 'index',
        'route_path' => 'components/index',
    ]);

    expect(fn () => DocumentationPage::factory()->for($snapshot, 'snapshot')->create([
        'source_path' => 'duplicate.md',
        'route_path' => 'concepts/index',
    ]))->toThrow(QueryException::class);
});

it('publishes a ready snapshot and supersedes the previous snapshot', function (): void {
    $version = PackageVersion::factory()->create();
    $previousSnapshot = DocumentationSnapshot::factory()->for($version)->active()->create();
    $version->update([
        'active_snapshot_id' => $previousSnapshot->getKey(),
        'source_commit' => $previousSnapshot->source_commit,
        'documentation_type' => $previousSnapshot->documentation_type,
    ]);

    $candidate = DocumentationSnapshot::factory()->for($version)->ready()->create([
        'documentation_type' => DocumentationType::Readme,
    ]);

    $publishedVersion = app(PublishSnapshot::class)->handle($version, $candidate);

    expect($publishedVersion->active_snapshot_id)->toBe($candidate->getKey())
        ->and($publishedVersion->source_commit)->toBe($candidate->source_commit)
        ->and($publishedVersion->documentation_type)->toBe(DocumentationType::Readme)
        ->and($publishedVersion->last_synced_at)->not->toBeNull()
        ->and($candidate->refresh()->state)->toBe(DocumentationSnapshotState::Active)
        ->and($candidate->published_at)->not->toBeNull()
        ->and($previousSnapshot->refresh()->state)->toBe(DocumentationSnapshotState::Superseded);
});

it('rejects snapshots owned by another version without changing publication state', function (): void {
    $version = PackageVersion::factory()->create();
    $activeSnapshot = DocumentationSnapshot::factory()->for($version)->active()->create();
    $version->update(['active_snapshot_id' => $activeSnapshot->getKey()]);

    $foreignSnapshot = DocumentationSnapshot::factory()->ready()->create();

    expect(fn () => app(PublishSnapshot::class)->handle($version, $foreignSnapshot))
        ->toThrow(LogicException::class)
        ->and($version->refresh()->active_snapshot_id)->toBe($activeSnapshot->getKey())
        ->and($activeSnapshot->refresh()->state)->toBe(DocumentationSnapshotState::Active)
        ->and($foreignSnapshot->refresh()->state)->toBe(DocumentationSnapshotState::Ready);
});

it('rejects a snapshot that is not ready', function (): void {
    $version = PackageVersion::factory()->create();
    $snapshot = DocumentationSnapshot::factory()->for($version)->create();

    expect(fn () => app(PublishSnapshot::class)->handle($version, $snapshot))
        ->toThrow(LogicException::class)
        ->and($version->refresh()->active_snapshot_id)->toBeNull()
        ->and($snapshot->refresh()->state)->toBe(DocumentationSnapshotState::Building);
});

it('cascades deletion through all snapshot-owned metadata', function (): void {
    $snapshot = DocumentationSnapshot::factory()->create();
    $page = DocumentationPage::factory()->for($snapshot, 'snapshot')->create();
    $section = DocumentationSection::factory()->create([
        'documentation_snapshot_id' => $snapshot->getKey(),
        'documentation_page_id' => $page->getKey(),
    ]);
    $redirect = DocumentationRedirect::factory()->for($snapshot, 'snapshot')->create();
    $navigationItem = DocumentationNavigationItem::factory()->for($snapshot, 'snapshot')->create();

    $snapshot->delete();

    $this->assertModelMissing($page);
    $this->assertModelMissing($section);
    $this->assertModelMissing($redirect);
    $this->assertModelMissing($navigationItem);
});

it('allows multiple package records to use one monorepo', function (): void {
    $repository = 'awcodes/packages';

    Package::factory()->create(['repository' => $repository]);
    Package::factory()->create(['repository' => $repository]);

    expect(Package::query()->where('repository', $repository)->count())->toBe(2);
});
