# Developer 3: Module C - Admin Panel (Filament Resources)

**Date**: 2026-01-05
**Phase**: Phase 4 - Admin Panel
**Estimated Time**: 6-8 hours
**Priority**: HIGH (Critical for internal management)

---

## 🎯 Mission

Build the complete **Filament Admin Panel** for internal staff to manage the transportation system. This includes full CRUD operations for businesses, vehicles, drivers, and pricing tiers, plus a dashboard with key metrics.

**Key Focus**: You own the ENTIRE admin panel. No dependencies on other developers.

---

## 📋 Your Module Ownership

### Files You Will Create (Complete Ownership)

```
app/Filament/Resources/BusinessResource.php
app/Filament/Resources/BusinessResource/Pages/ListBusinesses.php
app/Filament/Resources/BusinessResource/Pages/CreateBusiness.php
app/Filament/Resources/BusinessResource/Pages/EditBusiness.php
app/Filament/Resources/VehicleResource.php
app/Filament/Resources/VehicleResource/Pages/ListVehicles.php
app/Filament/Resources/VehicleResource/Pages/CreateVehicle.php
app/Filament/Resources/VehicleResource/Pages/EditVehicle.php
app/Filament/Resources/DriverResource.php
app/Filament/Resources/DriverResource/Pages/ListDrivers.php
app/Filament/Resources/DriverResource/Pages/CreateDriver.php
app/Filament/Resources/DriverResource/Pages/EditDriver.php
app/Filament/Resources/PricingTierResource.php
app/Filament/Resources/PricingTierResource/Pages/ListPricingTiers.php
app/Filament/Resources/PricingTierResource/Pages/CreatePricingTier.php
app/Filament/Resources/PricingTierResource/Pages/EditPricingTier.php
app/Filament/Widgets/StatsOverviewWidget.php
```

### Models You Will Use (Already Built)

- ✅ `Business` - Client companies
- ✅ `Vehicle` - Fleet vehicles
- ✅ `Driver` - Driver users
- ✅ `PricingTier` - Cost per KM tiers

**No Integration Needed**: Other developers are building APIs. You work completely independently.

---

## 🗂️ Resources You Will Build

### 1. BusinessResource
**Purpose**: Manage client businesses (ERP integrations)

**Features**:
- List view with search and filters
- Create/edit business details
- Generate API credentials
- View delivery request statistics
- Active/inactive toggle

**Fields**:
- Business name (required)
- Business type (bulk_order, pickup)
- Contact email (required, email)
- Contact phone (required)
- API key (auto-generated, read-only)
- Callback URL (URL)
- Callback API key
- Is active (boolean)
- Created/updated timestamps

**List View Columns**:
- Business name
- Business type (badge)
- Contact email
- Active status (badge)
- Total delivery requests count
- Created at

**Filters**:
- Business type
- Active status
- Created date range

**Actions**:
- Regenerate API key (with confirmation)
- View delivery requests (relation manager)

---

### 2. VehicleResource
**Purpose**: Manage fleet vehicles

**Features**:
- List view with KM tracking
- Create/edit vehicle details
- Track total KM and monthly KM
- Maintenance records link (future)
- Active/inactive toggle

**Fields**:
- Make (required, string, e.g., "VW")
- Model (required, string, e.g., "Caddy")
- Year (required, integer, 1900-current year)
- License plate (required, unique)
- Total KM driven (read-only, calculated)
- Monthly KM via app (read-only, calculated)
- Acquisition date (date)
- Is active (boolean)

**List View Columns**:
- Vehicle (Make + Model + Year)
- License plate
- Total KM driven
- Monthly KM via app
- Active status (badge)

**Filters**:
- Active status
- Acquisition year

**Info Box** (on edit page):
- Total trips completed
- Average KM per trip
- Last trip date

---

### 3. DriverResource
**Purpose**: Manage drivers (linked to User model)

**Features**:
- List view with assignments
- Create/edit driver details
- Assign to vehicle
- View trip history
- Active/inactive toggle

**Fields**:
- User (relationship to User model)
- Assigned vehicle (relationship to Vehicle)
- License number (required, unique)
- License expiry date (date)
- Is active (boolean)
- Notes (textarea)

**List View Columns**:
- Driver name (from User)
- Email (from User)
- Assigned vehicle
- License expiry date (with warning if < 30 days)
- Active status (badge)
- Total trips count

**Filters**:
- Assigned vehicle
- Active status
- License expiring soon (<30 days)

**Relation Managers**:
- Trips (list of all trips for this driver)

---

### 4. PricingTierResource
**Purpose**: Manage pricing tiers (cost per KM)

**Features**:
- List view with effective dates
- Create/edit pricing tiers
- Historical pricing records
- Active tier indicator

**Fields**:
- Business type (enum: bulk_order, pickup)
- Price per KM (decimal, required, min: 0)
- Effective date (date, required)
- Notes (textarea)

**List View Columns**:
- Business type (badge)
- Price per KM (formatted currency)
- Effective date
- Is current (badge if effective_date <= today, sorted desc)

**Filters**:
- Business type
- Effective date range

**Validation**:
- Cannot create tier with duplicate business_type + effective_date combination
- Effective date cannot be in the past (for new tiers)

---

### 5. Dashboard Widget (StatsOverviewWidget)
**Purpose**: Show key metrics on dashboard

**Stats Cards**:
1. **Total Active Businesses**
   - Count of active businesses
   - Icon: Building
   - Color: Success

2. **Trips Today**
   - Count of trips created today
   - Icon: Truck
   - Color: Info

3. **Active Vehicles**
   - Count of active vehicles
   - Icon: Car
   - Color: Warning

4. **Total KM This Month**
   - Sum of all trips' actual_km_driven for current month
   - Icon: Road
   - Color: Primary

---

## 📐 Detailed Implementation Guide

### Step 1: Generate Resources (30 minutes)

```bash
# Generate Business resource
php artisan make:filament-resource Business --generate

# Generate Vehicle resource
php artisan make:filament-resource Vehicle --generate

# Generate Driver resource
php artisan make:filament-resource Driver --generate

# Generate PricingTier resource
php artisan make:filament-resource PricingTier --generate

# Generate dashboard widget
php artisan make:filament-widget StatsOverviewWidget
```

---

### Step 2: BusinessResource Implementation (120 minutes)

#### app/Filament/Resources/BusinessResource.php

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BusinessType;
use App\Filament\Resources\BusinessResource\Pages;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Filament resource for managing businesses.
 */
class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Business Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Business Information')
                    ->schema([
                        Forms\Components\TextInput::make('business_name')
                            ->required()
                            ->maxLength(255)
                            ->label('Business Name'),

                        Forms\Components\Select::make('business_type')
                            ->required()
                            ->options(BusinessType::class)
                            ->native(false)
                            ->label('Business Type'),

                        Forms\Components\TextInput::make('contact_email')
                            ->required()
                            ->email()
                            ->maxLength(255)
                            ->label('Contact Email'),

                        Forms\Components\TextInput::make('contact_phone')
                            ->required()
                            ->tel()
                            ->maxLength(50)
                            ->label('Contact Phone'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('API Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('api_key')
                            ->label('API Key')
                            ->default(fn() => Str::random(64))
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-generated on creation. Use "Regenerate API Key" action to change.')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('regenerate')
                                    ->icon('heroicon-m-arrow-path')
                                    ->requiresConfirmation()
                                    ->action(function (Forms\Set $set) {
                                        $set('api_key', Str::random(64));
                                    })
                            ),

                        Forms\Components\TextInput::make('callback_url')
                            ->url()
                            ->maxLength(500)
                            ->label('Callback URL')
                            ->helperText('URL to send delivery completion notifications'),

                        Forms\Components\TextInput::make('callback_api_key')
                            ->maxLength(255)
                            ->label('Callback API Key')
                            ->helperText('Bearer token for callback authentication'),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')
                    ->searchable()
                    ->sortable()
                    ->label('Business Name'),

                Tables\Columns\TextColumn::make('business_type')
                    ->badge()
                    ->sortable()
                    ->label('Type'),

                Tables\Columns\TextColumn::make('contact_email')
                    ->searchable()
                    ->label('Email'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('deliveryRequests_count')
                    ->counts('deliveryRequests')
                    ->label('Delivery Requests')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business_type')
                    ->options(BusinessType::class)
                    ->label('Business Type'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All businesses')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created from'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('regenerateApiKey')
                    ->label('Regenerate API Key')
                    ->icon('heroicon-m-key')
                    ->requiresConfirmation()
                    ->action(function (Business $record) {
                        $record->update(['api_key' => Str::random(64)]);
                    })
                    ->successNotificationTitle('API key regenerated successfully'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinesses::route('/'),
            'create' => Pages\CreateBusiness::route('/create'),
            'edit' => Pages\EditBusiness::route('/{record}/edit'),
        ];
    }
}
```

---

### Step 3: VehicleResource Implementation (90 minutes)

#### app/Filament/Resources/VehicleResource.php

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Filament resource for managing vehicles.
 */
class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Fleet Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Vehicle Details')
                    ->schema([
                        Forms\Components\TextInput::make('make')
                            ->required()
                            ->maxLength(100)
                            ->label('Make')
                            ->placeholder('e.g., VW'),

                        Forms\Components\TextInput::make('model')
                            ->required()
                            ->maxLength(100)
                            ->label('Model')
                            ->placeholder('e.g., Caddy'),

                        Forms\Components\TextInput::make('year')
                            ->required()
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(now()->year + 1)
                            ->label('Year'),

                        Forms\Components\TextInput::make('license_plate')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->label('License Plate'),

                        Forms\Components\DatePicker::make('acquisition_date')
                            ->label('Acquisition Date'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Mileage Tracking')
                    ->schema([
                        Forms\Components\Placeholder::make('total_km_driven')
                            ->label('Total KM Driven')
                            ->content(fn(?Vehicle $record) => $record ? number_format($record->total_km_driven, 2) . ' km' : '0.00 km'),

                        Forms\Components\Placeholder::make('monthly_km_app')
                            ->label('Monthly KM via App')
                            ->content(fn(?Vehicle $record) => $record ? number_format($record->monthly_km_app, 2) . ' km' : '0.00 km'),
                    ])
                    ->columns(2)
                    ->hidden(fn(?Vehicle $record) => $record === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle')
                    ->label('Vehicle')
                    ->searchable(['make', 'model', 'year'])
                    ->formatStateUsing(fn(Vehicle $record) => "{$record->make} {$record->model} ({$record->year})"),

                Tables\Columns\TextColumn::make('license_plate')
                    ->searchable()
                    ->label('License Plate'),

                Tables\Columns\TextColumn::make('total_km_driven')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' km')
                    ->sortable()
                    ->label('Total KM'),

                Tables\Columns\TextColumn::make('monthly_km_app')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' km')
                    ->sortable()
                    ->label('Monthly KM'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('acquisition_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\Filter::make('acquisition_year')
                    ->form([
                        Forms\Components\Select::make('year')
                            ->options(function () {
                                $currentYear = now()->year;
                                return collect(range($currentYear - 20, $currentYear))
                                    ->mapWithKeys(fn($year) => [$year => $year]);
                            })
                            ->label('Acquisition Year'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['year'],
                            fn($q, $year) => $q->whereYear('acquisition_date', $year)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicles::route('/{record}/edit'),
        ];
    }
}
```

---

### Step 4: DriverResource Implementation (90 minutes)

#### app/Filament/Resources/DriverResource.php

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * Filament resource for managing drivers.
 */
class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Fleet Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Driver Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User Account')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->unique(User::class),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->required()
                                    ->dehydrateStateUsing(fn($state) => Hash::make($state)),
                            ])
                            ->required(),

                        Forms\Components\Select::make('vehicle_id')
                            ->label('Assigned Vehicle')
                            ->relationship('vehicle', 'license_plate')
                            ->getOptionLabelFromRecordUsing(fn(Vehicle $record) => "{$record->make} {$record->model} ({$record->license_plate})")
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\TextInput::make('license_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->label('License Number'),

                        Forms\Components\DatePicker::make('license_expiry_date')
                            ->required()
                            ->label('License Expiry Date')
                            ->after('today')
                            ->native(false),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),

                        Forms\Components\Textarea::make('notes')
                            ->maxLength(1000)
                            ->rows(3)
                            ->label('Notes'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Driver Name'),

                Tables\Columns\TextColumn::make('user.email')
                    ->searchable()
                    ->label('Email'),

                Tables\Columns\TextColumn::make('vehicle.license_plate')
                    ->label('Assigned Vehicle')
                    ->formatStateUsing(function (?Vehicle $state, Driver $record) {
                        if (! $record->vehicle) {
                            return '-';
                        }
                        return "{$record->vehicle->make} {$record->vehicle->model} ({$record->vehicle->license_plate})";
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('license_expiry_date')
                    ->date()
                    ->sortable()
                    ->label('License Expiry')
                    ->color(fn(Driver $record) => $record->license_expiry_date->diffInDays(now()) < 30 ? 'danger' : null)
                    ->icon(fn(Driver $record) => $record->license_expiry_date->diffInDays(now()) < 30 ? 'heroicon-m-exclamation-triangle' : null),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('trips_count')
                    ->counts('trips')
                    ->label('Total Trips')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->relationship('vehicle', 'license_plate')
                    ->label('Assigned Vehicle'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\Filter::make('license_expiring_soon')
                    ->label('License Expiring Soon (<30 days)')
                    ->query(fn($query) => $query->where('license_expiry_date', '<=', now()->addDays(30))),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDrivers::route('/create'),
            'edit' => Pages\EditDrivers::route('/{record}/edit'),
        ];
    }
}
```

---

### Step 5: PricingTierResource Implementation (90 minutes)

#### app/Filament/Resources/PricingTierResource.php

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BusinessType;
use App\Filament\Resources\PricingTierResource\Pages;
use App\Models\PricingTier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Filament resource for managing pricing tiers.
 */
class PricingTierResource extends Resource
{
    protected static ?string $model = PricingTier::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Business Management';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pricing Information')
                    ->schema([
                        Forms\Components\Select::make('business_type')
                            ->required()
                            ->options(BusinessType::class)
                            ->native(false)
                            ->label('Business Type'),

                        Forms\Components\TextInput::make('price_per_km')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(9999.99)
                            ->step(0.01)
                            ->prefix('JOD')
                            ->label('Price per KM'),

                        Forms\Components\DatePicker::make('effective_date')
                            ->required()
                            ->native(false)
                            ->label('Effective Date')
                            ->default(now())
                            ->afterOrEqual('today')
                            ->helperText('The date from which this pricing tier becomes active.'),

                        Forms\Components\Textarea::make('notes')
                            ->maxLength(1000)
                            ->rows(3)
                            ->label('Notes')
                            ->placeholder('Optional notes about this pricing tier'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_type')
                    ->badge()
                    ->sortable()
                    ->label('Business Type'),

                Tables\Columns\TextColumn::make('price_per_km')
                    ->money('JOD')
                    ->sortable()
                    ->label('Price per KM'),

                Tables\Columns\TextColumn::make('effective_date')
                    ->date()
                    ->sortable()
                    ->label('Effective Date'),

                Tables\Columns\TextColumn::make('is_current')
                    ->label('Current')
                    ->badge()
                    ->formatStateUsing(fn(PricingTier $record) => $record->effective_date <= now() ? 'Yes' : 'Future')
                    ->color(fn(PricingTier $record) => $record->effective_date <= now() ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('effective_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('business_type')
                    ->options(BusinessType::class)
                    ->label('Business Type'),

                Tables\Filters\Filter::make('effective_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Effective from'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Effective until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('effective_date', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('effective_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPricingTiers::route('/'),
            'create' => Pages\CreatePricingTiers::route('/create'),
            'edit' => Pages\EditPricingTiers::route('/{record}/edit'),
        ];
    }
}
```

---

### Step 6: Dashboard Widget (60 minutes)

#### app/Filament/Widgets/StatsOverviewWidget.php

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Business;
use App\Models\Trip;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard stats overview widget.
 */
class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Businesses', Business::where('is_active', true)->count())
                ->description('Client companies')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),

            Stat::make('Trips Today', Trip::whereDate('created_at', today())->count())
                ->description('Scheduled for today')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Active Vehicles', Vehicle::where('is_active', true)->count())
                ->description('Fleet vehicles')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning'),

            Stat::make('Total KM This Month', number_format(
                Trip::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('actual_km_driven'),
                2
            ) . ' km')
                ->description('Distance driven this month')
                ->descriptionIcon('heroicon-m-map')
                ->color('primary'),
        ];
    }
}
```

Register the widget in `app/Filament/Pages/Dashboard.php` (or equivalent):

```php
<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
        ];
    }
}
```

---

## ✅ Success Criteria

By end of day, you must have:

### Code Deliverables
- ✅ 4 Filament Resources (Business, Vehicle, Driver, PricingTier)
- ✅ 12 Resource Pages (List, Create, Edit for each resource)
- ✅ 1 Dashboard Widget (StatsOverviewWidget)
- ✅ All forms with proper validation
- ✅ All tables with search, filters, and sorting

### Functionality Working
- ✅ Business CRUD with API key generation
- ✅ Vehicle CRUD with KM tracking display
- ✅ Driver CRUD with user creation and vehicle assignment
- ✅ PricingTier CRUD with effective date validation
- ✅ Dashboard showing 4 key metrics
- ✅ Search functionality on all list views
- ✅ Filters working on all resources
- ✅ Proper form validation preventing invalid data

### Testing & Quality
- ✅ Manual testing: Create, edit, delete records in each resource
- ✅ Verify filters work correctly
- ✅ Verify search functionality
- ✅ Test API key regeneration
- ✅ Test dashboard widget displays correct counts
- ✅ PSR-12 compliant code
- ✅ Full PHPDoc coverage

---

## 🚫 What NOT to Do

**Do NOT**:
- ❌ Modify files owned by other developers (Business API, Driver API)
- ❌ Create API endpoints (that's Developer 1 & 2's job)
- ❌ Skip validation rules
- ❌ Hardcode values (use enums and relationships)
- ❌ Create overly complex forms (keep it simple)
- ❌ Skip search/filter functionality

---

## 🧪 Testing Checklist

### Manual Testing Steps:

**Business Resource**:
- [ ] Create new business → API key auto-generated
- [ ] Edit business → Change business type
- [ ] Regenerate API key → Confirms and generates new key
- [ ] Filter by business type → Shows correct records
- [ ] Search by business name → Finds correct business
- [ ] Delete business → Removes from list

**Vehicle Resource**:
- [ ] Create new vehicle → All fields saved
- [ ] View total KM → Shows read-only field
- [ ] Filter by active status → Shows only active/inactive
- [ ] Search by license plate → Finds correct vehicle
- [ ] Edit vehicle → Changes saved

**Driver Resource**:
- [ ] Create driver with new user → User created + driver record
- [ ] Assign vehicle → Dropdown shows vehicles
- [ ] License expiry date in <30 days → Shows warning color
- [ ] Filter by vehicle → Shows drivers for that vehicle
- [ ] Search by driver name → Finds correct driver

**PricingTier Resource**:
- [ ] Create pricing tier → Effective date validation works
- [ ] Current tier shows "Yes" badge → Effective date <= today
- [ ] Filter by business type → Shows correct tiers
- [ ] Edit pricing tier → Changes saved
- [ ] Verify cannot create duplicate business_type + effective_date

**Dashboard**:
- [ ] Active Businesses count → Matches database
- [ ] Trips Today count → Shows today's trips only
- [ ] Active Vehicles count → Matches active vehicles
- [ ] Total KM This Month → Sum of actual_km_driven

---

## 💡 Implementation Tips

1. **Use `--generate` flag** - Filament can auto-generate basic CRUD
2. **Customize after generation** - Add custom fields, filters, actions
3. **Test as you build** - Create test records after each resource
4. **Use enums** - Leverage existing BusinessType enum
5. **Follow Filament docs** - https://filamentphp.com/docs/4.x
6. **Keep forms simple** - Don't overcomplicate, MVP first

---

## 📚 Reference Documentation

**Filament 4 Docs**:
- Resources: https://filamentphp.com/docs/4.x/panels/resources
- Forms: https://filamentphp.com/docs/4.x/forms/fields
- Tables: https://filamentphp.com/docs/4.x/tables/columns
- Widgets: https://filamentphp.com/docs/4.x/widgets/stats-overview

**Existing Patterns**:
Look at any existing Filament resources in `app/Filament/Resources/` for patterns to follow.

---

## 🎯 Workflow Recommendation

**Morning (2 hours)**:
1. Generate all 4 resources with `--generate`
2. Test basic CRUD works for each
3. Customize Business resource forms and tables

**Midday (2 hours)**:
4. Customize Vehicle resource with KM tracking
5. Customize Driver resource with user creation
6. Add filters to all resources

**Afternoon (3 hours)**:
7. Customize PricingTier resource with validation
8. Create dashboard widget
9. Manual testing of all resources
10. Fix any issues, polish UI

---

**Good luck! You own the Admin Panel. Make it user-friendly! 🎨**

**Remember**: Your Scrum Master is here to help. Ask questions early!

---

**Task File Created**: 2026-01-05 @ 00:00 UTC
