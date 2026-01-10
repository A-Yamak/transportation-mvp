<?php

namespace App\Filament\Resources\Drivers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Driver Information')
                    ->description('Basic driver details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('User Account')
                                    ->relationship('user', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('User account for authentication'),

                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required()
                                    ->maxLength(20),
                            ]),

                        TextInput::make('license_number')
                            ->label('License Number')
                            ->required()
                            ->maxLength(50)
                            ->columnSpanFull(),
                    ]),

                Section::make('Assignment')
                    ->description('Vehicle and rate configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('vehicle_id')
                                    ->label('Assigned Vehicle')
                                    ->relationship('vehicle', 'model')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Vehicle this driver operates'),

                                TextInput::make('price_per_km')
                                    ->label('Price per KM')
                                    ->numeric()
                                    ->prefix('$')
                                    ->helperText('Driver rate per kilometer'),
                            ]),
                    ]),

                Section::make('Status')
                    ->description('Active/inactive configuration')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive drivers cannot be assigned trips'),
                    ]),
            ]);
    }
}
