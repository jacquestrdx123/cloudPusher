<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\InviteCompanyMember;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addExistingUser')
                ->label('Add existing user')
                ->visible(fn (): bool => Filament::getTenant() !== null)
                ->form([
                    TextInput::make('phone')
                        ->tel()
                        ->helperText('E.164 format (e.g. +27821234567).')
                        ->requiredWithout('email'),
                    TextInput::make('email')
                        ->email()
                        ->requiredWithout('phone'),
                ])
                ->action(function (array $data, InviteCompanyMember $inviteCompanyMember): void {
                    $tenant = Filament::getTenant();
                    /** @var User $actor */
                    $actor = auth()->user();

                    if ($tenant === null || $actor === null) {
                        return;
                    }

                    try {
                        $result = $inviteCompanyMember->handle($tenant, $actor, [
                            'phone' => filled($data['phone'] ?? null)
                                ? PhoneNumber::normalize((string) $data['phone'])
                                : null,
                            'email' => $data['email'] ?? null,
                        ]);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(collect($exception->errors())->flatten()->first() ?: 'Unable to add user.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title($result['created']
                            ? 'User added to this company.'
                            : 'User is already a member of this company.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
