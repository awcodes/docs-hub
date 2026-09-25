<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectVersions\Tables;

use App\Enums\DocumentationType;
use App\Enums\VersionStatus;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->git_ref === $record->version
                        ? null
                        : "ref: {$record->git_ref}"),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                TextColumn::make('documentation_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('pages_count')
                    ->label('Pages')
                    ->counts('pages'),

                // Staleness is the question this table exists to answer while
                // synchronization is manual: nothing runs docs:sync unless
                // somebody does.
                TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->since()
                    ->sortable()
                    ->placeholder('Never')
                    ->tooltip(fn ($record): ?string => $record->last_synced_at?->toDayDateTimeString()),
                TextColumn::make('active_snapshot')
                    ->label('Snapshot')
                    ->limit(12)
                    ->color('gray')
                    ->placeholder('Nothing published')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(VersionStatus::class),
                SelectFilter::make('documentation_type')
                    ->label('Type')
                    ->options(DocumentationType::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('project.name');
    }
}
