<?php

namespace App\Filament\Resources\Trips\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Trip ID')
                    ->searchable()
                    ->sortable()
                    ->limit(8),

                TextColumn::make('driver.user.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vehicle.model')
                    ->label('Vehicle')
                    ->searchable(),

                TextColumn::make('trip_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'delivery' => 'info',
                        'waste_collection' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => str($state?->value ?? $state)->replace('_', ' ')->title())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'pending' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => str($state?->value ?? $state)->replace('_', ' ')->title())
                    ->sortable(),

                TextColumn::make('actual_km')
                    ->label('Actual KM')
                    ->numeric(decimalPlaces: 1)
                    ->suffix(' km')
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('M d, H:i')
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('M d, H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('trip_type')
                    ->label('Type')
                    ->options([
                        'delivery' => 'Delivery',
                        'waste_collection' => 'Waste Collection',
                    ]),

                TernaryFilter::make('has_completed')
                    ->label('Completed')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('completed_at'),
                        false: fn ($query) => $query->whereNull('completed_at'),
                    ),

                TernaryFilter::make('has_started')
                    ->label('Started')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('started_at'),
                        false: fn ($query) => $query->whereNull('started_at'),
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
