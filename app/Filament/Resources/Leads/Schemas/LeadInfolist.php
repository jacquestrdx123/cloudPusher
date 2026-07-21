<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('company_name')
                    ->label('Company'),
                TextEntry::make('phone')
                    ->placeholder('—'),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (LeadStatus $state): string => $state->label())
                    ->color(fn (LeadStatus $state): string => match ($state) {
                        LeadStatus::New => 'warning',
                        LeadStatus::Contacted => 'info',
                        LeadStatus::Closed => 'gray',
                    }),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('message')
                    ->columnSpanFull(),
                TextEntry::make('notes')
                    ->label('Admin notes')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }
}
