<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Filament\Resources\Drivers\Tables\DriversTable;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDrivers extends ListRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Driver')
                ->icon('heroicon-m-plus'),
        ];
    }

    protected function getTableColumns(): array
    {
        return DriversTable::configure($this->table)->getColumns();
    }

    protected function getTableFilters(): array
    {
        return DriversTable::configure($this->table)->getFilters();
    }
}
