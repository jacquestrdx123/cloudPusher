<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('email')
                    ->email()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('company_name')
                    ->label('Company')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('phone')
                    ->tel()
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('message')
                    ->rows(4)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(collect(LeadStatus::cases())
                        ->mapWithKeys(fn (LeadStatus $status): array => [$status->value => $status->label()])
                        ->all())
                    ->required(),
                Textarea::make('notes')
                    ->label('Admin notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
