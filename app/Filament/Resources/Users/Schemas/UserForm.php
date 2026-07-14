<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
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
                    ->helperText('Leave empty for a platform administrator.')
                    ->visible(fn (): bool => Filament::getTenant() === null)
                    ->dehydrated(fn (): bool => Filament::getTenant() === null),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('locale')
                    ->maxLength(10)
                    ->placeholder('en'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Only needed for users who sign in to a panel.'),
                Toggle::make('is_admin')
                    ->label('Platform administrator')
                    ->helperText('Grants access to all companies in this admin panel.')
                    ->visible(fn (): bool => (bool) auth()->user()->is_admin),
            ]);
    }
}
