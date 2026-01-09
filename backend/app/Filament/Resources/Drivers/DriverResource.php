<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Filament\Resources\Drivers\Schemas\DriverForm;
use App\Filament\Resources\Drivers\Tables\DriversTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-circle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function getLabel(): ?string
    {
        return 'Driver';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Drivers';
    }

    public static function table(Table $table): Table
    {
        return DriversTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'edit' => EditDriver::route('/{record}/edit'),
        ];
    }
}
