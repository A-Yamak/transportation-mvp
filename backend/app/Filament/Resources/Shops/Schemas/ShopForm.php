<?php

namespace App\Filament\Resources\Shops\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->description('Shop details and identification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('external_shop_id')
                                    ->label('External Shop ID')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Unique ID from Melo ERP'),

                                TextInput::make('name')
                                    ->label('Shop Name')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        TextInput::make('address')
                            ->label('Address')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Section::make('Location')
                    ->description('GPS coordinates for mapping and routing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('lat')
                                    ->label('Latitude')
                                    ->numeric()
                                    ->required()
                                    ->rules(['between:-90,90'])
                                    ->helperText('Between -90 and 90'),

                                TextInput::make('lng')
                                    ->label('Longitude')
                                    ->numeric()
                                    ->required()
                                    ->rules(['between:-180,180'])
                                    ->helperText('Between -180 and 180'),
                            ]),
                    ]),

                Section::make('Contact Information')
                    ->description('Shop owner/manager contact details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('contact_name')
                                    ->label('Contact Name')
                                    ->maxLength(255),

                                TextInput::make('contact_phone')
                                    ->label('Contact Phone')
                                    ->tel()
                                    ->maxLength(20),
                            ]),
                    ]),

                Section::make('Settings')
                    ->description('Shop operational settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('track_waste')
                                    ->label('Track Waste Collection')
                                    ->helperText('Enable waste tracking for this shop'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->helperText('Inactive shops won\'t appear in delivery assignments'),
                            ]),
                    ]),
            ]);
    }
}
