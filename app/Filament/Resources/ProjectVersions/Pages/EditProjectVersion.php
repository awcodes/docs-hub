<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectVersions\Pages;

use App\Filament\Resources\ProjectVersions\ProjectVersionResource;
use App\Models\ProjectVersion;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProjectVersion extends EditRecord
{
    protected static string $resource = ProjectVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var ProjectVersion $version */
        $version = $this->record;

        ProjectVersionResource::clearOtherDefaults($version);
    }
}
