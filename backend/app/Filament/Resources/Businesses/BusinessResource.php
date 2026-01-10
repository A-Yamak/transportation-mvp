<?php

namespace App\Filament\Resources\Businesses;

use App\Filament\Resources\Businesses\Pages\CreateBusiness;
use App\Filament\Resources\Businesses\Pages\EditBusiness;
use App\Filament\Resources\Businesses\Pages\ListBusinesses;
use App\Filament\Resources\Businesses\Schemas\BusinessForm;
use App\Filament\Resources\Businesses\Tables\BusinessesTable;
use App\Models\Business;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-office';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuration';
    }

    public static function getModelLabel(): string
    {
        return 'Business';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Businesses';
    }

    public static function form(Schema $schema): Schema
    {
        return BusinessForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinesses::route('/'),
            'create' => CreateBusiness::route('/create'),
            'edit' => EditBusiness::route('/{record}/edit'),
        ];
    }
}
