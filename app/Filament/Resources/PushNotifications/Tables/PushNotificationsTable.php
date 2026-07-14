<?php

namespace App\Filament\Resources\PushNotifications\Tables;

use App\Models\PushNotification;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PushNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => Filament::getTenant() === null),
                TextColumn::make('target_type')
                    ->label('Target')
                    ->badge()
                    ->formatStateUsing(fn (PushNotification $record): string => $record->target_type === PushNotification::TARGET_USER
                        ? 'User: '.$record->user->name
                        : 'Group: '.$record->group->name),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('channels')
                    ->badge()
                    ->separator(','),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PushNotification::STATUS_SENT => 'success',
                        PushNotification::STATUS_FAILED => 'danger',
                        PushNotification::STATUS_PROCESSING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('recipients_count')
                    ->label('Recipients')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        PushNotification::STATUS_PENDING => 'Pending',
                        PushNotification::STATUS_PROCESSING => 'Processing',
                        PushNotification::STATUS_SENT => 'Sent',
                        PushNotification::STATUS_FAILED => 'Failed',
                    ]),
                SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->visible(fn (): bool => Filament::getTenant() === null),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
