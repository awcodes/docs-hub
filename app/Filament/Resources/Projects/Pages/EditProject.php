<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Concerns\SynchronizesDocumentation;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProject extends EditRecord
{
    use SynchronizesDocumentation;

    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->syncAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * Synchronize every version of this project.
     *
     * No confirmation: a sync cannot damage what is being served. Everything
     * before the pointer update happens on a snapshot nobody is reading, so the
     * worst outcome is the previous snapshot still being served and an
     * unreferenced directory left behind.
     */
    private function syncAction(): Action
    {
        /** @var Project $project */
        $project = $this->record;

        return Action::make('sync')
            ->label('Synchronize')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->disabled(fn (): bool => ! $project->versions()->exists())
            ->tooltip(fn (): ?string => $project->versions()->exists()
                ? null
                : 'Register a version first — there is nothing to publish.')
            ->action(fn () => $this->synchronize($project->versions()->with('project')->get()));
    }
}
