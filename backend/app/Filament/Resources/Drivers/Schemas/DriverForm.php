<?php

namespace App\Filament\Resources\Drivers\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Personal Information')
                    ->description('Driver contact and identification details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Full Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->unique('users', 'email', ignoreRecord: true)
                                    ->maxLength(255),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone_number')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required()
                                    ->maxLength(20),

                                TextInput::make('identification_number')
                                    ->label('ID Number')
                                    ->maxLength(50)
                                    ->helperText('License or Passport number'),
                            ]),
                    ]),

                Section::make('Assignment')
                    ->description('Vehicle and status assignment')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('assigned_vehicle_id')
                                    ->label('Assigned Vehicle')
                                    ->relationship('assignedVehicle', 'model')
                                    ->required()
                                    ->helperText('Select the vehicle this driver operates'),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'on_leave' => 'On Leave',
                                    ])
                                    ->required()
                                    ->default('active'),
                            ]),
                    ]),

                Section::make('Documentation')
                    ->description('License and certification dates')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('license_expiry_date')
                                    ->label('License Expiry')
                                    ->type('date')
                                    ->helperText('When driver license expires'),

                                TextInput::make('notes')
                                    ->label('Notes')
                                    ->placeholder('Any additional notes about driver')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Performance Metrics')
                    ->description('Driver performance tracking (read-only)')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_km_driven')
                                    ->label('Total KM Driven')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric(),

                                TextInput::make('monthly_km_app')
                                    ->label('Monthly KM (App)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric(),

                                TextInput::make('completed_trips')
                                    ->label('Completed Trips')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric(),
                            ]),
                    ]),
            ]);
    }
}
