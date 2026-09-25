<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectVersions;

use App\Filament\Resources\ProjectVersions\Pages\CreateProjectVersion;
use App\Filament\Resources\ProjectVersions\Pages\EditProjectVersion;
use App\Filament\Resources\ProjectVersions\Pages\ListProjectVersions;
use App\Filament\Resources\ProjectVersions\Schemas\ProjectVersionForm;
use App\Filament\Resources\ProjectVersions\Tables\ProjectVersionsTable;
use App\Models\ProjectVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The versions a project publishes, each read from one Git ref.
 *
 * Only the upper half of the record is editable here. `last_synced_at`,
 * `source_commit`, `active_snapshot` and `documentation_type` belong to
 * synchronization, and `navigation`, `redirects` and `manifest_title` are
 * written from the manifest — editing any of them by hand would describe a
 * snapshot that does not exist. They are shown, because they are the first
 * thing anyone debugging a sync wants to read.
 */
class ProjectVersionResource extends Resource
{
    protected static ?string $model = ProjectVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Documentation';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'version';

    public static function form(Schema $schema): Schema
    {
        return ProjectVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectVersions::route('/'),
            'create' => CreateProjectVersion::route('/create'),
            'edit' => EditProjectVersion::route('/{record}/edit'),
        ];
    }

    /**
     * Keep at most one default version per project.
     *
     * `Project::defaultVersion()` is a `hasOne` over `is_default`, so a second
     * one does not fail — it makes which version answers `/example/installation`
     * depend on row order. Enforced after the save rather than in the form,
     * because the field being cleared belongs to a different record.
     */
    public static function clearOtherDefaults(ProjectVersion $version): void
    {
        if (! $version->is_default) {
            return;
        }

        ProjectVersion::query()
            ->where('project_id', $version->project_id)
            ->whereKeyNot($version->getKey())
            ->update(['is_default' => false]);
    }
}
