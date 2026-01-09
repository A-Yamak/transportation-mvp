<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\Shops\Tables\ShopsTable;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShops extends ListRecords
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Shop')
                ->icon('heroicon-m-plus'),
        ];
    }

    protected function getTableColumns(): array
    {
        return ShopsTable::configure($this->table)->getColumns();
    }

    protected function getTableFilters(): array
    {
        return ShopsTable::configure($this->table)->getFilters();
    }
}
