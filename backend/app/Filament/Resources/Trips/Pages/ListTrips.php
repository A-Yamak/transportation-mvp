<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Trips\Tables\TripsTable;
use Filament\Resources\Pages\ListRecords;

class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;

    protected function getTableColumns(): array
    {
        return TripsTable::configure($this->table)->getColumns();
    }

    protected function getTableFilters(): array
    {
        return TripsTable::configure($this->table)->getFilters();
    }
}
