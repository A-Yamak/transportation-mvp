<?php

namespace App\Filament\Resources\Shops\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_shop_id')
                    ->label('Shop ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Shop Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('address')
                    ->label('Address')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable(),

                TextColumn::make('contact_phone')
                    ->label('Phone')
                    ->searchable(),

                IconColumn::make('track_waste')
                    ->label('Waste Tracking')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('last_synced_at')
                    ->label('Last Synced')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('business_id')
                    ->label('Business')
                    ->relationship('business', 'name'),

                TernaryFilter::make('track_waste')
                    ->label('Waste Tracking Enabled')
                    ->queries(
                        true: fn ($query) => $query->where('track_waste', true),
                        false: fn ($query) => $query->where('track_waste', false),
                    ),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                    ),
            ])
            ->actions([
                // Actions defined in resource pages
            ])
            ->bulkActions([
                // Bulk actions can be added here if needed
            ])
            ->defaultSort('created_at', 'desc');
    }
}
