<?php

namespace App\Filament\Resources\Businesses\Pages;

use App\Filament\Resources\Businesses\BusinessResource;
use App\Filament\Resources\Businesses\Tables\BusinessesTable;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBusinesses extends ListRecords
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Business')
                ->icon('heroicon-m-plus'),
        ];
    }

    protected function getTableColumns(): array
    {
        return BusinessesTable::configure($this->table)->getColumns();
    }

    protected function getTableFilters(): array
    {
        return BusinessesTable::configure($this->table)->getFilters();
    }
}
