<?php

namespace App\Filament\Resources\Trips;

use App\Filament\Resources\Trips\Pages\ListTrips;
use App\Filament\Resources\Trips\Pages\ViewTrip;
use App\Filament\Resources\Trips\Tables\TripsTable;
use App\Models\Trip;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function getModelLabel(): string
    {
        return 'Trip';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Trips';
    }

    public static function table(Table $table): Table
    {
        return TripsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrips::route('/'),
            'view' => ViewTrip::route('/{record}'),
        ];
    }
}
