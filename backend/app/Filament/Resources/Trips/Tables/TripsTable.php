<?php

namespace App\Filament\Resources\Trips\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
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
                    ->sortable(),

                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vehicle.model')
                    ->label('Vehicle')
                    ->searchable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'info' => 'pending',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn($state) => str($state)->replace('_', ' ')->title())
                    ->sortable(),

                TextColumn::make('total_km')
                    ->label('Planned KM')
                    ->numeric(decimalPlaces: 1)
                    ->suffix(' km')
                    ->sortable(),

                TextColumn::make('actual_km')
                    ->label('Actual KM')
                    ->numeric(decimalPlaces: 1)
                    ->suffix(' km')
                    ->sortable(),

                TextColumn::make('estimated_cost')
                    ->label('Cost')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->sortable(),

                TextColumn::make('destinations_count')
                    ->label('Stops')
                    ->counts('destinations')
                    ->sortable(),

                TextColumn::make('completed_destinations_count')
                    ->label('Completed')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('M d, H:i')
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('M d, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),

                SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'name'),

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
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                // Bulk actions can be added here if needed
            ])
            ->defaultSort('assigned_at', 'desc');
    }
}
