<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\PhoneNumber;
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
                    ->tel()
                    ->unique(ignoreRecord: true)
                    ->helperText('E.164 format (e.g. +27821234567). Used for the mobile app login.')
                    ->dehydrateStateUsing(function (?string $state): ?string {
                        if (! filled($state)) {
                            return null;
                        }

                        return PhoneNumber::normalize($state) ?: null;
                    }),
                TextInput::make('locale')
                    ->maxLength(10)
                    ->placeholder('en'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Required for panel login and the mobile app.'),
                Toggle::make('is_admin')
                    ->label('Global administrator')
                    ->helperText('Full access to every company in this admin panel.')
                    ->visible(fn (): bool => auth()->user()?->isGlobalAdmin() === true),
                Toggle::make('is_company_admin')
                    ->label('Company administrator')
                    ->helperText('Can manage this company: users, groups, registrations, and notifications.')
                    ->dehydrated(false)
                    ->visible(fn (): bool => auth()->user()?->isGlobalAdmin() === true || auth()->user()?->isCompanyAdmin() === true),
            ]);
    }
}
