<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn ($record) => $record->email_verified_at !== null),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->since(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->nullable()
                    ->placeholder('All users')
                    ->trueLabel('Verified')
                    ->falseLabel('Unverified'),
            ])
            ->actions([
                // Actions defined in resource pages
            ])
            ->bulkActions([
                // Bulk actions can be added here if needed
            ])
            ->defaultSort('created_at', 'desc');
    }
}
