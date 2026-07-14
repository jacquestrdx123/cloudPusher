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
                    ->formatStateUsing(function (PushNotification $record): string {
                        return match ($record->target_type) {
                            PushNotification::TARGET_USER => 'User: '.$record->user->name,
                            PushNotification::TARGET_GROUP => 'Group: '.$record->group->name,
                            PushNotification::TARGET_BROADCAST => 'Broadcast',
                            default => $record->target_type,
                        };
                    }),
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
                        PushNotification::STATUS_PARTIAL => 'warning',
                        PushNotification::STATUS_FAILED => 'danger',
                        PushNotification::STATUS_PROCESSING => 'warning',
                        PushNotification::STATUS_SCHEDULED => 'info',
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
                        PushNotification::STATUS_SCHEDULED => 'Scheduled',
                        PushNotification::STATUS_PROCESSING => 'Processing',
                        PushNotification::STATUS_SENT => 'Sent',
                        PushNotification::STATUS_PARTIAL => 'Partial',
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
