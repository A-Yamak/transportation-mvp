<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Trips\Schemas\TripForm;
use Filament\Resources\Pages\ViewRecord;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    protected function getFormSchema(): array
    {
        return TripForm::configure($this->form)->getComponents();
    }
}
