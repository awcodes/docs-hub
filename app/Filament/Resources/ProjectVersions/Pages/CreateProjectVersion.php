<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectVersions\Pages;

use App\Filament\Resources\ProjectVersions\ProjectVersionResource;
use App\Models\ProjectVersion;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectVersion extends CreateRecord
{
    protected static string $resource = ProjectVersionResource::class;

    protected function afterCreate(): void
    {
        /** @var ProjectVersion $version */
        $version = $this->record;

        ProjectVersionResource::clearOtherDefaults($version);
    }
}
