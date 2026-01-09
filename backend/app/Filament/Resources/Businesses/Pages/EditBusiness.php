<?php

namespace App\Filament\Resources\Businesses\Pages;

use App\Filament\Resources\Businesses\BusinessResource;
use App\Filament\Resources\Businesses\Schemas\BusinessForm;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBusiness extends EditRecord
{
    protected static string $resource = BusinessResource::class;

    protected function getFormSchema(): array
    {
        return BusinessForm::configure($this->form)->getComponents();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Delete Business')
                ->icon('heroicon-m-trash'),
        ];
    }
}
