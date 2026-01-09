<?php

namespace App\Filament\Resources\Drivers\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Driver Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),

                TextColumn::make('assignedVehicle.model')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'on_leave',
                    ])
                    ->sortable(),

                TextColumn::make('total_km_driven')
                    ->label('Total KM')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),

                TextColumn::make('monthly_km_app')
                    ->label('Monthly KM')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),

                TextColumn::make('trips_count')
                    ->label('Completed Trips')
                    ->numeric()
                    ->counts('completedTrips')
                    ->sortable(),

                TextColumn::make('license_expiry_date')
                    ->label('License Expires')
                    ->date('M d, Y')
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
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'on_leave' => 'On Leave',
                    ]),

                SelectFilter::make('assigned_vehicle_id')
                    ->label('Vehicle')
                    ->relationship('assignedVehicle', 'model'),

                TernaryFilter::make('has_license_expiry')
                    ->label('License Expiry Date Set')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('license_expiry_date'),
                        false: fn ($query) => $query->whereNull('license_expiry_date'),
                    ),
            ])
            ->actions([
                // Actions defined in resource pages
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                // Bulk actions can be added here if needed
            ])
            ->defaultSort('created_at', 'desc');
    }
}
