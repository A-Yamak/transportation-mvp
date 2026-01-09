<?php

namespace App\Filament\Resources\Businesses\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                    ->sortable(),

                BadgeColumn::make('business_type')
                    ->label('Type')
                    ->colors([
                        'info' => 'bulk_order',
                        'warning' => 'pickup',
                    ])
                    ->formatStateUsing(fn($state) => match($state) {
                        'bulk_order' => 'ERP Bulk',
                        'pickup' => 'Pickup',
                        default => $state
                    })
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('contact_person')
                    ->label('Contact Person')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),

                TextColumn::make('deliveryRequests_count')
                    ->label('Delivery Requests')
                    ->counts('deliveryRequests')
                    ->sortable(),

                TextColumn::make('drivers_count')
                    ->label('Drivers')
                    ->counts('drivers')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('business_type')
                    ->label('Business Type')
                    ->options([
                        'bulk_order' => 'ERP Bulk Order',
                        'pickup' => 'Pickup',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                    ),

                TernaryFilter::make('has_callback_url')
                    ->label('Callback Configured')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('callback_url'),
                        false: fn ($query) => $query->whereNull('callback_url'),
                    ),
            ])
            ->actions([
                // Actions defined in resource pages
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                // Bulk actions can be added here if needed
            ])
            ->defaultSort('created_at', 'desc');
    }
}
