<?php

namespace App\Filament\Resources\DeviceTokens\Schemas;

use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DeviceTokenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User')
                    ->relationship(
                        name: 'user',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $tenant = Filament::getTenant();

                            if ($tenant instanceof Company) {
                                return $query->whereBelongsTo($tenant, 'company');
                            }

                            return $query;
                        },
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('platform')
                    ->options([
                        'fcm' => 'FCM (Android / Web)',
                        'apns' => 'APNs (iOS)',
                    ])
                    ->required(),
                TextInput::make('token')
                    ->required()
                    ->maxLength(500),
                TextInput::make('name')
                    ->label('Device name')
                    ->maxLength(255),
                DateTimePicker::make('last_used_at'),
            ]);
    }
}
