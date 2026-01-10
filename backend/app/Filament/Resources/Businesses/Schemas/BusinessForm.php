<?php

namespace App\Filament\Resources\Businesses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->description('Business details and contact information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Business Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Legal business name'),

                                Select::make('business_type')
                                    ->label('Business Type')
                                    ->options([
                                        'bulk_order' => 'Bulk Order (ERP Integration)',
                                        'pickup' => 'Pickup (Driver Collects)',
                                    ])
                                    ->required()
                                    ->native(false),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label('Contact Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('phone_number')
                                    ->label('Contact Phone')
                                    ->tel()
                                    ->maxLength(20),
                            ]),

                        TextInput::make('contact_person')
                            ->label('Contact Person Name')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('API Credentials')
                    ->description('Integration credentials for delivery requests')
                    ->schema([
                        TextInput::make('api_key')
                            ->label('API Key (B2B Integration)')
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Used to authenticate delivery request submissions'),

                        TextInput::make('callback_api_key')
                            ->label('Callback API Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Key used in webhook callbacks from our system'),
                    ]),

                Section::make('Callback Configuration')
                    ->description('Webhook URL for delivery completion notifications')
                    ->schema([
                        TextInput::make('callback_url')
                            ->label('Callback URL')
                            ->url()
                            ->maxLength(500)
                            ->helperText('HTTP endpoint where we send delivery callbacks'),

                        TextInput::make('payload_schema_name')
                            ->label('Payload Schema')
                            ->placeholder('e.g., erp_v1')
                            ->maxLength(100)
                            ->helperText('Custom field mapping schema (optional)'),
                    ]),

                Section::make('Status')
                    ->description('Active/inactive configuration')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Can receive delivery requests when active'),
                    ]),
            ]);
    }
}
