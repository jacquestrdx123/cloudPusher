<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
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
                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (LeadStatus $state): string => $state->label())
                    ->color(fn (LeadStatus $state): string => match ($state) {
                        LeadStatus::New => 'warning',
                        LeadStatus::Contacted => 'info',
                        LeadStatus::Closed => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(LeadStatus::cases())
                        ->mapWithKeys(fn (LeadStatus $status): array => [$status->value => $status->label()])
                        ->all())
                    ->default(LeadStatus::New->value),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
