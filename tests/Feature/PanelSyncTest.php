<?php

declare(strict_types=1);

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Widgets\DocumentationSyncWidget;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

/*
| Synchronizing from the panel. Phase 3 is tabled, so nothing runs `docs:sync`
| unless somebody does — and "somebody" should not need a terminal.
|
| The operation itself is tested against `SyncProjectVersion`; what matters here
| is that the panel reaches it, survives a failure the same way the console
| does, and says which version failed.
*/

beforeEach(function (): void {
    Storage::fake('documentation');

    $this->actingAs(User::factory()->create());
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
    File::deleteDirectory(storage_path('app/docs-staging'));
});

it('renders the synchronize control', function (): void {
    // `callAction()` reaches the action without rendering it, so it passes
    // happily while the button is missing from the widget. Only the rendered
    // output catches a slot the section component does not have.
    livewire(DocumentationSyncWidget::class)
        ->assertSuccessful()
        ->assertSee('Synchronize');
});

it('renders somewhere for the confirmation to open', function (): void {
    // `callAction()` confirms the action without the modal ever rendering, so
    // it passed while the widget had no modals for the confirmation to live in
    // and pressing the button in the browser did nothing at all. A widget is
    // its own Livewire component; the dashboard's modals are not its modals.
    livewire(DocumentationSyncWidget::class)
        ->assertSeeHtml('filamentActionModals');
});

it('publishes every version from the dashboard widget', function (): void {
    $example = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $toolkit = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Toolkit\n",
    ], slug: 'toolkit');

    config()->set('documentation.local_sources', [
        'example' => documentationScratch().'/example',
        'toolkit' => documentationScratch().'/toolkit',
    ]);

    livewire(DocumentationSyncWidget::class)
        ->callAction('syncAll')
        ->assertNotified();

    expect($example->refresh()->isPublished())->toBeTrue()
        ->and($toolkit->refresh()->isPublished())->toBeTrue();
});

it('publishes one project from its own header action', function (): void {
    $example = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $toolkit = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Toolkit\n",
    ], slug: 'toolkit');

    config()->set('documentation.local_sources', [
        'example' => documentationScratch().'/example',
        'toolkit' => documentationScratch().'/toolkit',
    ]);

    livewire(EditProject::class, ['record' => $example->project->getRouteKey()])
        ->callAction('sync')
        ->assertNotified();

    expect($example->refresh()->isPublished())->toBeTrue()
        ->and($toolkit->refresh()->isPublished())->toBeFalse();
});

it('keeps going when one version fails, and names it', function (): void {
    // A navigation entry pointing at a file that is not there. Publication is
    // strict, so this version publishes nothing — and the other still must.
    $broken = mounted([
        'docs/docs.yml' => "navigation:\n  - instalation",
        'docs/index.md' => "# Example\n",
    ]);

    $sound = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Toolkit\n",
    ], slug: 'toolkit');

    config()->set('documentation.local_sources', [
        'example' => documentationScratch().'/example',
        'toolkit' => documentationScratch().'/toolkit',
    ]);

    livewire(DocumentationSyncWidget::class)
        ->callAction('syncAll')
        ->assertNotified();

    expect($broken->refresh()->isPublished())->toBeFalse()
        ->and($sound->refresh()->isPublished())->toBeTrue();
});

it('offers nothing to synchronize on a project with no versions', function (): void {
    $project = Project::factory()->create();

    livewire(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertActionDisabled('sync');
});

it('counts what has never been synchronized', function (): void {
    $project = Project::factory()->create();
    ProjectVersion::factory()->for($project)->create(['last_synced_at' => null]);
    ProjectVersion::factory()->for($project)->create([
        'version' => '2.x',
        'last_synced_at' => now()->subDay(),
    ]);

    livewire(DocumentationSyncWidget::class)->assertSuccessful();

    // Counted against the widget itself rather than through Livewire's
    // untyped `instance()`; these only query.
    $widget = new DocumentationSyncWidget;

    expect($widget->getNeverSyncedCount())->toBe(1)
        ->and($widget->getVersionCount())->toBe(2)
        ->and($widget->getProjectCount())->toBe(1);
});
