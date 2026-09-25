<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectKind;
use App\Enums\VersioningMode;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): string => $record->slug),
                TextColumn::make('repository')
                    ->searchable()
                    ->color('gray'),
                TextColumn::make('kind')
                    ->badge()
                    ->sortable(),
                TextColumn::make('group')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('versioning_mode')
                    ->label('Versioning')
                    ->toggleable(),

                // A project with no default version cannot answer an
                // unversioned URL at all, which is a registry error rather
                // than a reader's problem — so it is visible from the list
                // rather than only discoverable by opening the project.
                TextColumn::make('versions_count')
                    ->label('Versions')
                    ->counts('versions')
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'gray' : 'danger')
                    ->tooltip(fn ($state): ?string => $state > 0 ? null : 'No versions registered, so nothing can be published.'),

                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->options(ProjectKind::class),
                SelectFilter::make('versioning_mode')
                    ->label('Versioning')
                    ->options(VersioningMode::class),
                TernaryFilter::make('is_visible')
                    ->label('Visible'),
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
            ->defaultSort('name');
    }
}
