<?php

namespace App\Filament\Resources\Financial;

use App\Filament\Resources\Financial\Pages\Dashboard;
use Filament\Resources\Resource;

class FinancialResource extends Resource
{
    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-currency-dollar';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reporting';
    }

    protected static ?string $label = 'Financial Dashboard';

    protected static ?string $pluralLabel = 'Financial Dashboard';

    public static function getPages(): array
    {
        return [
            'index' => Dashboard::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
