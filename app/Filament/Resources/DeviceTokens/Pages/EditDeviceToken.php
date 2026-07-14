<?php

namespace App\Filament\Resources\DeviceTokens\Pages;

use App\Filament\Resources\DeviceTokens\DeviceTokenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeviceToken extends EditRecord
{
    protected static string $resource = DeviceTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
