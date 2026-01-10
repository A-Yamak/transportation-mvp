<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Period Info -->
        <x-filament::section>
            <x-slot name="heading">Current Period</x-slot>
            <p class="text-2xl font-bold">{{ $period }}</p>
        </x-filament::section>

        <!-- Revenue Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::section>
                <x-slot name="heading">Total Revenue</x-slot>
                <p class="text-3xl font-bold text-success-600">${{ number_format($totalRevenue, 2) }}</p>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Completed Trips</x-slot>
                <p class="text-3xl font-bold text-primary-600">{{ $completedTripsCount }}</p>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Avg Revenue/Trip</x-slot>
                <p class="text-3xl font-bold text-purple-600">${{ number_format($averageRevenuePerTrip, 2) }}</p>
            </x-filament::section>
        </div>

        <!-- Costs Breakdown -->
        <x-filament::section>
            <x-slot name="heading">Costs Breakdown</x-slot>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Fuel Expenses</p>
                    <p class="text-2xl font-bold text-danger-600">${{ number_format($fuelCosts, 2) }}</p>
                </div>

                <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Driver Payments</p>
                    <p class="text-2xl font-bold text-warning-600">${{ number_format($driverPayments, 2) }}</p>
                </div>

                <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Maintenance</p>
                    <p class="text-2xl font-bold text-warning-600">${{ number_format($maintenanceCosts, 2) }}</p>
                </div>

                <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Costs</p>
                    <p class="text-2xl font-bold text-danger-600">${{ number_format($totalCosts, 2) }}</p>
                </div>
            </div>
        </x-filament::section>

        <!-- Profitability Analysis -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::section>
                <x-slot name="heading">Net Profit</x-slot>
                <p class="text-3xl font-bold {{ $netProfit >= 0 ? 'text-success-600' : 'text-danger-600' }}">${{ number_format($netProfit, 2) }}</p>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Profit Margin</x-slot>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($profitMarginPercentage, 1) }}%</p>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Cost Ratio</x-slot>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($costRatioPercentage, 1) }}%</p>
            </x-filament::section>
        </div>

        <!-- KM Efficiency -->
        <x-filament::section>
            <x-slot name="heading">KM Efficiency Metrics</x-slot>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-lg bg-info-50 dark:bg-info-900/20">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total KM Driven</p>
                    <p class="text-2xl font-bold text-info-600">{{ number_format($totalKmDriven, 1) }} km</p>
                </div>

                <div class="p-4 rounded-lg bg-info-50 dark:bg-info-900/20">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Avg KM/Trip</p>
                    <p class="text-2xl font-bold text-info-600">{{ number_format($averageKmPerTrip, 1) }} km</p>
                </div>

                <div class="p-4 rounded-lg bg-info-50 dark:bg-info-900/20">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Revenue/KM</p>
                    <p class="text-2xl font-bold text-info-600">${{ number_format($revenuePerKm, 2) }}</p>
                </div>
            </div>
        </x-filament::section>

        <!-- Footer Note -->
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <strong>Note:</strong> This dashboard displays financial metrics for <strong>{{ $period }}</strong>.
                Metrics are calculated from completed trips and journal entries.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
