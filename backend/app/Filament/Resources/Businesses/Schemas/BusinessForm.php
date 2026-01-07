<?php

namespace App\Filament\Resources\Businesses\Schemas;

use App\Enums\BusinessType;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Business Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('business_type')
                            ->options(BusinessType::class)
                            ->default('bulk_order')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                    ]),

                Section::make('API Configuration')
                    ->description('API credentials for integrating with this business')
                    ->schema([
                        TextInput::make('api_key')
                            ->label('API Key')
                            ->required()
                            ->default(fn () => 'melo_' . Str::random(32))
                            ->readOnly()
                            ->copyable()
                            ->helperText('This API key will be used by the business to authenticate API requests')
                            ->suffixAction(
                                Action::make('regenerate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->requiresConfirmation()
                                    ->action(function ($set) {
                                        $set('api_key', 'melo_' . Str::random(32));
                                    })
                            ),
                    ]),

                Section::make('Callback Configuration')
                    ->description('Where to send delivery status updates')
                    ->schema([
                        TextInput::make('callback_url')
                            ->label('Callback URL')
                            ->url()
                            ->placeholder('https://erp.example.com/api/delivery-callback')
                            ->helperText('Our system will POST delivery status updates to this URL'),
                        TextInput::make('callback_api_key')
                            ->label('Callback API Key')
                            ->placeholder('Optional: API key to send in callback requests')
                            ->helperText('If provided, we will include this in the Authorization header when calling your callback URL'),
                    ]),
            ]);
    }
}
