<?php

namespace App\Filament\Resources\UserGroups;

use App\Filament\Resources\UserGroups\Pages\CreateUserGroup;
use App\Filament\Resources\UserGroups\Pages\EditUserGroup;
use App\Filament\Resources\UserGroups\Pages\ListUserGroups;
use App\Filament\Resources\UserGroups\RelationManagers\UsersRelationManager;
use App\Filament\Resources\UserGroups\Schemas\UserGroupForm;
use App\Filament\Resources\UserGroups\Tables\UserGroupsTable;
use App\Models\UserGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserGroupResource extends Resource
{
    protected static ?string $model = UserGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return UserGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserGroups::route('/'),
            'create' => CreateUserGroup::route('/create'),
            'edit' => EditUserGroup::route('/{record}/edit'),
        ];
    }
}
