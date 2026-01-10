<?php

namespace App\Filament\Resources\Drivers\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Driver Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),

                TextColumn::make('vehicle.model')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('license_number')
                    ->label('License #')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),

                TextColumn::make('trips_count')
                    ->label('Trips')
                    ->counts('trips')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                    ),

                SelectFilter::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'model'),
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
