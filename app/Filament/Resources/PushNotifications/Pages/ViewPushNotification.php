<?php

namespace App\Filament\Resources\PushNotifications\Pages;

use App\Filament\Resources\PushNotifications\PushNotificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPushNotification extends ViewRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
