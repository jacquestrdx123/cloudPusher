<?php

namespace App\Filament\Resources\DeviceTokens\Pages;

use App\Filament\Resources\DeviceTokens\DeviceTokenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeviceTokens extends ListRecords
{
    protected static string $resource = DeviceTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
