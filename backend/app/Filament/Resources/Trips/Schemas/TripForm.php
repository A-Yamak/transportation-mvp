<?php

namespace App\Filament\Resources\Trips\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Trip Information')
                    ->description('Trip details (read-only)')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('id')
                                    ->label('Trip ID')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('status')
                                    ->label('Status')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),

                Section::make('Assignment')
                    ->description('Driver and vehicle details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('driver.name')
                                    ->label('Driver')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('vehicle.model')
                                    ->label('Vehicle')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),

                Section::make('Route Metrics')
                    ->description('Distance and cost calculations')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_km')
                                    ->label('Planned KM')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('actual_km')
                                    ->label('Actual KM')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('estimated_cost')
                                    ->label('Estimated Cost')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefix('$'),
                            ]),
                    ]),

                Section::make('Destinations')
                    ->description('Delivery stops on this trip')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('destinations_count')
                                    ->label('Total Stops')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric(),

                                TextInput::make('completed_destinations_count')
                                    ->label('Completed Stops')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric(),
                            ]),
                    ]),

                Section::make('Timeline')
                    ->description('Trip execution timeline')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('assigned_at')
                                    ->label('Assigned At')
                                    ->type('datetime-local')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('started_at')
                                    ->label('Started At')
                                    ->type('datetime-local')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('completed_at')
                                    ->label('Completed At')
                                    ->type('datetime-local')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('failed_at')
                                    ->label('Failed At')
                                    ->type('datetime-local')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),
            ]);
    }
}
