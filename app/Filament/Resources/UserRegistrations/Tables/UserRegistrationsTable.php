<?php

namespace App\Filament\Resources\UserRegistrations\Tables;

use App\Actions\ApproveUserRegistration;
use App\Actions\RejectUserRegistration;
use App\Enums\UserRegistrationStatus;
use App\Models\User;
use App\Models\UserRegistration;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class UserRegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserRegistrationStatus $state): string => $state->label())
                    ->color(fn (UserRegistrationStatus $state): string => match ($state) {
                        UserRegistrationStatus::Pending => 'warning',
                        UserRegistrationStatus::Approved => 'success',
                        UserRegistrationStatus::Rejected => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(UserRegistrationStatus::cases())
                        ->mapWithKeys(fn (UserRegistrationStatus $status) => [$status->value => $status->label()])
                        ->all())
                    ->default(UserRegistrationStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (UserRegistration $record): bool => $record->isPending())
                    ->form([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (UserRegistration $record, array $data): void {
                        /** @var User $reviewer */
                        $reviewer = auth()->user();

                        try {
                            app(ApproveUserRegistration::class)->handle(
                                $record,
                                $reviewer,
                                $data['notes'] ?? null,
                            );

                            Notification::make()
                                ->title('Registration approved')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (UserRegistration $record): bool => $record->isPending())
                    ->form([
                        Textarea::make('notes')
                            ->label('Reason')
                            ->rows(2)
                            ->required(),
                    ])
                    ->action(function (UserRegistration $record, array $data): void {
                        /** @var User $reviewer */
                        $reviewer = auth()->user();

                        try {
                            app(RejectUserRegistration::class)->handle(
                                $record,
                                $reviewer,
                                $data['notes'] ?? null,
                            );

                            Notification::make()
                                ->title('Registration rejected')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
