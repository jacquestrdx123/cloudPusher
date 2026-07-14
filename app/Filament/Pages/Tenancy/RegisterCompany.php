<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'New company';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('Used in the webhook URL. Leave blank to generate from the name.')
                    ->maxLength(255),
                Select::make('default_channels')
                    ->label('Default channels')
                    ->multiple()
                    ->options([
                        'push' => 'Push (FCM / APNs)',
                        'mail' => 'Email',
                        'sms' => 'SMS',
                    ])
                    ->default(['push']),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Model
    {
        return Company::create($data);
    }
}
