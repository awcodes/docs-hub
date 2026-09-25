<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Concerns\SynchronizesDocumentation;
use App\Models\Project;
use App\Models\ProjectVersion;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Synchronizing every registered version, from the dashboard.
 *
 * This exists because synchronization is manual: phase 3 is tabled, so nothing
 * runs `docs:sync` unless somebody does, and "somebody" should not have to open
 * a terminal. It also answers the question that goes with that — how stale is
 * any of this — because a button to sync without a reason to press it is just a
 * button.
 */
class DocumentationSyncWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use SynchronizesDocumentation;

    protected string $view = 'filament.widgets.documentation-sync';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function syncAllAction(): Action
    {
        return Action::make('syncAll')
            ->label('Synchronize')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Synchronize every version')
            ->modalDescription('Each registered version is read from its source and republished if its commit has moved. A version that fails leaves the documentation it is already serving untouched.')
            ->modalSubmitActionLabel('Synchronize')
            ->action(fn () => $this->synchronize(ProjectVersion::query()->with('project')->get()));
    }

    public function getProjectCount(): int
    {
        return Project::query()->count();
    }

    public function getVersionCount(): int
    {
        return ProjectVersion::query()->count();
    }

    /** Versions that have never been synchronized publish nothing at all. */
    public function getNeverSyncedCount(): int
    {
        return ProjectVersion::query()->whereNull('last_synced_at')->count();
    }

    public function getLastSyncedAt(): ?CarbonInterface
    {
        return ProjectVersion::query()->max('last_synced_at') === null
            ? null
            : ProjectVersion::query()->orderByDesc('last_synced_at')->first()?->last_synced_at;
    }
}
