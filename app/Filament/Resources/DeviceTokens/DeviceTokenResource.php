<?php

namespace App\Filament\Resources\DeviceTokens;

use App\Filament\Resources\DeviceTokens\Pages\CreateDeviceToken;
use App\Filament\Resources\DeviceTokens\Pages\EditDeviceToken;
use App\Filament\Resources\DeviceTokens\Pages\ListDeviceTokens;
use App\Filament\Resources\DeviceTokens\Schemas\DeviceTokenForm;
use App\Filament\Resources\DeviceTokens\Tables\DeviceTokensTable;
use App\Models\DeviceToken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeviceTokenResource extends Resource
{
    protected static ?string $model = DeviceToken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    /**
     * Device tokens belong to a company through users, so tenant scoping is
     * applied via {@see \App\Http\Middleware\ApplyTenantScopes}.
     */
    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return DeviceTokenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeviceTokensTable::configure($table);
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
            'index' => ListDeviceTokens::route('/'),
            'create' => CreateDeviceToken::route('/create'),
            'edit' => EditDeviceToken::route('/{record}/edit'),
        ];
    }
}
