<?php

namespace App\Filament\Resources\Businesses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Business Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('business_type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('api_key')
                    ->label('API Key')
                    ->copyable()
                    ->copyMessage('API Key copied!')
                    ->fontFamily('mono')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->api_key),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('callback_url')
                    ->label('Callback URL')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->callback_url)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
