<?php

namespace App\Filament\Resources\UserGroups\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (): bool => Filament::getTenant() === null)
                    ->dehydrated(fn (): bool => Filament::getTenant() === null),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('Leave blank to generate from the name.')
                    ->maxLength(255),
            ]);
    }
}
