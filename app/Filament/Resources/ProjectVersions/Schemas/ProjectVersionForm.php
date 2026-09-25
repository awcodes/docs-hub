<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectVersions\Schemas;

use App\Enums\DocumentationType;
use App\Enums\VersionStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

class ProjectVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['md' => 2])
            ->components([
                Section::make('Version')
                    ->columns(['md' => 2])
                    ->columnSpanFull()
                    ->components([
                        Select::make('project_id')
                            ->relationship('project', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        TextInput::make('version')
                            ->required()
                            ->live(onBlur: true)
                            // The ref is the same string often enough that
                            // typing it twice is the common case, and differs
                            // rarely enough that it must stay editable.
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (blank($get('git_ref'))) {
                                    $set('git_ref', $state);
                                }
                            })
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, Get $get) => $rule->where('project_id', $get('project_id')),
                            )
                            ->helperText('What the reader sees in the URL — 1.x.'),
                        TextInput::make('git_ref')
                            ->label('Git ref')
                            ->required()
                            ->helperText('The branch or tag it is read from. Usually the same as the version.'),
                        Select::make('status')
                            ->options(VersionStatus::class)
                            ->default(VersionStatus::Current)
                            ->required()
                            ->helperText('Legacy shows the reader a notice. Separate from which version is default.'),
                        Toggle::make('is_default')
                            ->label('Default version')
                            ->columnSpanFull()
                            ->helperText('Where an unversioned URL lands. A project with no default cannot answer one at all, and setting this clears it on the project\'s other versions.'),
                    ]),

                // Written by synchronization, never by hand: editing these
                // would describe a snapshot that does not exist.
                Section::make('Synchronization')
                    ->description('Written by docs:sync. Shown for debugging, not for editing.')
                    ->columns(['md' => 2])
                    ->columnSpanFull()
                    ->hiddenOn(Operation::Create)
                    ->components([
                        TextInput::make('last_synced_at')
                            ->label('Last synced')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Never'),
                        TextInput::make('documentation_type')
                            ->label('Documentation type')
                            ->disabled()
                            ->dehydrated(false)
                            // Disabled fields are hydrated from the raw
                            // attribute rather than the cast, so this arrives
                            // as the backing string.
                            ->formatStateUsing(function (mixed $state): ?string {
                                $type = $state instanceof DocumentationType
                                    ? $state
                                    : DocumentationType::tryFrom((string) $state);

                                return $type?->getLabel();
                            }),
                        TextInput::make('source_commit')
                            ->label('Source commit')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Not yet synchronized'),
                        TextInput::make('active_snapshot')
                            ->label('Active snapshot')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Nothing published')
                            ->helperText('The published snapshot readers are served. Publication is a pointer update, so rolling back is a matter of moving this.'),
                    ]),
            ]);
    }
}
