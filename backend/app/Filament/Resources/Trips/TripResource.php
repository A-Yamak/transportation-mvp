<?php

namespace App\Filament\Resources\Trips;

use App\Filament\Resources\Trips\Pages\ListTrips;
use App\Filament\Resources\Trips\Pages\ViewTrip;
use App\Filament\Resources\Trips\Schemas\TripForm;
use App\Filament\Resources\Trips\Tables\TripsTable;
use App\Models\Trip;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getLabel(): ?string
    {
        return 'Trip';
    }

    public static function getPluralLabel(): ?string
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
