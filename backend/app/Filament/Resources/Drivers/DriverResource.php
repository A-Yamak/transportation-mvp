<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Filament\Resources\Drivers\Schemas\DriverForm;
use App\Filament\Resources\Drivers\Tables\DriversTable;
use App\Models\Driver;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-circle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function getModelLabel(): string
    {
        return 'Driver';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Drivers';
    }

    public static function form(Schema $schema): Schema
    {
        return DriverForm::configure($schema);
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
