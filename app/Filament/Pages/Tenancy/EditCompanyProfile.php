<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

class EditCompanyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Company settings';
    }

    public function form(Schema $schema): Schema
    {
        $isGlobalAdmin = auth()->user()?->isGlobalAdmin() === true;

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('Used in registration URLs, webhook URLs, and tenant routes.')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled(fn (): bool => ! $isGlobalAdmin)
                    ->dehydrated(),
                Select::make('default_channels')
                    ->label('Default channels')
                    ->helperText('Used when a webhook payload does not specify channels.')
                    ->multiple()
                    ->options([
                        'push' => 'Push (FCM / APNs)',
                        'mail' => 'Email',
                        'sms' => 'SMS',
                    ]),
                TextInput::make('hmac_secret')
                    ->label('Company API token / HMAC secret')
                    ->helperText('Use as Authorization: Bearer for /api/v1 routes, or sign webhook bodies with HMAC-SHA256.')
                    ->readOnly()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Inactive companies cannot accept registrations or API traffic.')
                    ->disabled(fn (): bool => ! $isGlobalAdmin)
                    ->dehydrated(),
            ]);
    }
}
