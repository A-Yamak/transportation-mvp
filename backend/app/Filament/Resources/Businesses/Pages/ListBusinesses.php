<?php

namespace App\Filament\Resources\Businesses\Pages;

use App\Filament\Resources\Businesses\BusinessResource;
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
}
