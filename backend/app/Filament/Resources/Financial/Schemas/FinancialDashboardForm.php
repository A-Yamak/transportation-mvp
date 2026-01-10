<?php

namespace App\Filament\Resources\Financial\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FinancialDashboardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Revenue')
                    ->description('Delivery and trip revenue metrics')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_revenue')
                                    ->label('Total Revenue')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$'),

                                TextInput::make('completed_trips_count')
                                    ->label('Completed Trips')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric(),

                                TextInput::make('average_revenue_per_trip')
                                    ->label('Avg Revenue/Trip')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ]),

                Section::make('Costs')
                    ->description('Operating expenses breakdown')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('fuel_costs')
                                    ->label('Fuel Expenses')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$'),

                                TextInput::make('driver_payments')
                                    ->label('Driver Payments')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$'),

                                TextInput::make('maintenance_costs')
                                    ->label('Maintenance')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$'),
                            ]),

                        TextInput::make('total_costs')
                            ->label('Total Operating Costs')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric()
                            ->prefix('$'),
                    ]),

                Section::make('Profitability')
                    ->description('Net profit and margin analysis')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('net_profit')
                                    ->label('Net Profit')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$'),

                                TextInput::make('profit_margin_percentage')
                                    ->label('Profit Margin %')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->suffix('%'),

                                TextInput::make('cost_ratio_percentage')
                                    ->label('Cost Ratio %')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->suffix('%'),
                            ]),
                    ]),

                Section::make('KM Efficiency')
                    ->description('Distance and cost per kilometer metrics')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_km_driven')
                                    ->label('Total KM Driven')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->suffix(' km'),

                                TextInput::make('average_km_per_trip')
                                    ->label('Avg KM/Trip')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->suffix(' km'),

                                TextInput::make('revenue_per_km')
                                    ->label('Revenue/KM')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ]),

                Section::make('Period')
                    ->description('Metrics are calculated for current month')
                    ->schema([
                        TextInput::make('period')
                            ->label('Current Period')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn() => now()->format('F Y')),
                    ]),
            ]);
    }
}
