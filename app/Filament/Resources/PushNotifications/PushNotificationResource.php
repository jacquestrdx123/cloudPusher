<?php

namespace App\Filament\Resources\PushNotifications;

use App\Filament\Resources\PushNotifications\Pages\ListPushNotifications;
use App\Filament\Resources\PushNotifications\Pages\ViewPushNotification;
use App\Filament\Resources\PushNotifications\RelationManagers\DeliveriesRelationManager;
use App\Filament\Resources\PushNotifications\Schemas\PushNotificationForm;
use App\Filament\Resources\PushNotifications\Tables\PushNotificationsTable;
use App\Models\PushNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PushNotificationResource extends Resource
{
    protected static ?string $model = PushNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Notification log';

    public static function form(Schema $schema): Schema
    {
        return PushNotificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PushNotificationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPushNotifications::route('/'),
            'view' => ViewPushNotification::route('/{record}'),
        ];
    }
}
