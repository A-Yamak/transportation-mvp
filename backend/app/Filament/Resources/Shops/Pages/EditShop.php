<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\Shops\Schemas\ShopForm;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    protected function getFormSchema(): array
    {
        return ShopForm::configure($this->form)->getComponents();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Delete Shop')
                ->icon('heroicon-m-trash'),
        ];
    }
}
