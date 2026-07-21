<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('companies.name')
                    ->label('Companies')
                    ->badge()
                    ->separator(',')
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

                        $tenant = Filament::getTenant();

                        if ($tenant !== null && $record->isCompanyAdminOf($tenant)) {
                            return 'Company admin';
                        }

                        if ($tenant === null && $record->isCompanyAdmin()) {
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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_company_admin')
                    ->label('Company admin')
                    ->queries(
                        true: fn ($query) => $query->whereHas(
                            'companies',
                            function ($companies): void {
                                $tenant = Filament::getTenant();
                                $companies->wherePivot('is_company_admin', true);

                                if ($tenant !== null) {
                                    $companies->whereKey($tenant->getKey());
                                }
                            },
                        ),
                        false: fn ($query) => $query->whereDoesntHave(
                            'companies',
                            function ($companies): void {
                                $tenant = Filament::getTenant();
                                $companies->wherePivot('is_company_admin', true);

                                if ($tenant !== null) {
                                    $companies->whereKey($tenant->getKey());
                                }
                            },
                        ),
                    ),
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
