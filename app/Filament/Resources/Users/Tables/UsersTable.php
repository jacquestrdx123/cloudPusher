<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->isGlobalAdmin() === true && Filament::getTenant() === null),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->state(function (User $record): string {
                        if ($record->isGlobalAdmin()) {
                            return 'Global admin';
                        }

                        if ($record->isCompanyAdmin()) {
                            return 'Company admin';
                        }

                        return 'Member';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Global admin' => 'danger',
                        'Company admin' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('device_tokens_count')
                    ->label('Devices')
                    ->counts('deviceTokens'),
                IconColumn::make('is_admin')
                    ->label('Global')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_company_admin')
                    ->label('Company admin')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->visible(fn (): bool => auth()->user()?->isGlobalAdmin() === true && Filament::getTenant() === null),
                TernaryFilter::make('is_company_admin')
                    ->label('Company admin'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
