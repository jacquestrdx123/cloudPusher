<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('Used in the webhook URL. Leave blank to generate from the name.')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('default_channels')
                    ->label('Default channels')
                    ->helperText('Used when a webhook payload does not specify channels.')
                    ->multiple()
                    ->options([
                        'push' => 'Push (FCM / APNs)',
                        'mail' => 'Email',
                        'sms' => 'SMS',
                    ])
                    ->default(['push']),
                TextInput::make('hmac_secret')
                    ->label('Company API token / HMAC secret')
                    ->helperText('Use as Authorization: Bearer for /api/v1 routes, or sign webhook bodies with HMAC-SHA256.')
                    ->readOnly()
                    ->default(fn () => Str::random(48))
                    ->suffixAction(
                        Action::make('regenerate')
                            ->icon('heroicon-o-arrow-path')
                            ->requiresConfirmation()
                            ->action(fn (Set $set) => $set('hmac_secret', Str::random(48)))
                    )
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
