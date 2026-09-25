<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectVersions\Pages;

use App\Filament\Resources\ProjectVersions\ProjectVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectVersions extends ListRecords
{
    protected static string $resource = ProjectVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
