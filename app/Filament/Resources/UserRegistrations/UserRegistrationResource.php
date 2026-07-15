<?php

namespace App\Filament\Resources\UserRegistrations;

use App\Filament\Resources\UserRegistrations\Pages\ListUserRegistrations;
use App\Filament\Resources\UserRegistrations\Tables\UserRegistrationsTable;
use App\Models\UserRegistration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserRegistrationResource extends Resource
{
    protected static ?string $model = UserRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Registrations';

    protected static ?string $modelLabel = 'Registration';

    protected static ?string $pluralModelLabel = 'Registrations';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return UserRegistrationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserRegistrations::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
