<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\Shops\Schemas\ShopForm;
use Filament\Resources\Pages\CreateRecord;

class CreateShop extends CreateRecord
{
    protected static string $resource = ShopResource::class;

    protected function getFormSchema(): array
    {
        return ShopForm::configure($this->form)->getComponents();
    }
}
