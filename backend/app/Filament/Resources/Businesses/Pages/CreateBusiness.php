<?php

namespace App\Filament\Resources\Businesses\Pages;

use App\Filament\Resources\Businesses\BusinessResource;
use App\Filament\Resources\Businesses\Schemas\BusinessForm;
use Filament\Resources\Pages\CreateRecord;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    protected function getFormSchema(): array
    {
        return BusinessForm::configure($this->form)->getComponents();
    }
}
