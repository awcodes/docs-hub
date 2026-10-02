<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\ProjectVersions\ProjectVersionResource;
use App\Filament\Resources\ProjectVersions\Schemas\ProjectVersionForm;
use App\Filament\Resources\ProjectVersions\Tables\ProjectVersionsTable;
use App\Models\ProjectVersion;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * A project's versions, managed from the project they belong to.
 *
 * The form and table are `ProjectVersionResource`'s own, so a version reads
 * and validates the same wherever it is edited. What differs is that the
 * project is already known here: the shared schema hides its project picker
 * and column when it finds itself inside a relation manager.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $recordTitleAttribute = 'version';

    public function form(Schema $schema): Schema
    {
        return ProjectVersionForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        // Actions are restated rather than inherited because here they open
        // modals, and a modal save bypasses the resource pages' `afterSave()`
        // that keeps one default per project.
        return ProjectVersionsTable::configure($table)
            ->defaultSort('version')
            ->headerActions([
                CreateAction::make()
                    ->after(fn (ProjectVersion $record) => ProjectVersionResource::clearOtherDefaults($record)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->after(fn (ProjectVersion $record) => ProjectVersionResource::clearOtherDefaults($record)),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
