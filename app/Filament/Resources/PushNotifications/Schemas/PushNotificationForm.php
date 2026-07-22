<?php

namespace App\Filament\Resources\PushNotifications\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PushNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                TextInput::make('target_type')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('user_group_id')
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('body')
                    ->columnSpanFull(),
                TextInput::make('image_url')
                    ->label('Image URL')
                    ->columnSpanFull(),
                TextInput::make('sound'),
                TextInput::make('category'),
                TextInput::make('android_channel_id')
                    ->label('Android channel ID'),
                Textarea::make('data')
                    ->columnSpanFull(),
                Textarea::make('channels')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('recipients_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
