@extends('filament::page')

@section('content')
    <div class="space-y-6">
        <!-- Period Info -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <h3 class="text-sm font-semibold text-gray-700">Period</h3>
            <p class="text-2xl font-bold text-gray-900">{{ $this->period }}</p>
        </div>

        <!-- Revenue Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow p-6 border-t-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-3xl font-bold text-green-600 mt-2">${{ number_format($this->totalRevenue, 2) }}</p>
                    </div>
                    <svg class="w-12 h-12 text-green-500 opacity-20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.16 2.75a.75.75 0 00-1.32 0l-.5 1.436A2.25 2.25 0 015.347 5.75h-.5a.75.75 0 000 1.5h.5a2.25 2.25 0 010 4.5h-.5a.75.75 0 000 1.5h.5A2.25 2.25 0 016.84 15.814l.5 1.436a.75.75 0 101.32 0l.5-1.436A2.25 2.25 0 0111.25 14.25h.5a.75.75 0 000-1.5h-.5a2.25 2.25 0 010-4.5h.5a.75.75 0 000-1.5h-.5A2.25 2.25 0 018.66 4.186l-.5-1.436z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-t-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Completed Trips</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2">{{ $this->completedTripsCount }}</p>
                    </div>
                    <svg class="w-12 h-12 text-blue-500 opacity-20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM15 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2zM5 13a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM15 13a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-t-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Avg Revenue/Trip</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2">${{ number_format($this->averageRevenuePerTrip, 2) }}</p>
                    </div>
                    <svg class="w-12 h-12 text-purple-500 opacity-20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Costs Breakdown -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Costs Breakdown</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-6">
                <div class="bg-red-50 rounded-lg p-4 border-l-4 border-red-500">
                    <p class="text-sm text-gray-600">Fuel Expenses</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">${{ number_format($this->fuelCosts, 2) }}</p>
                </div>

                <div class="bg-orange-50 rounded-lg p-4 border-l-4 border-orange-500">
                    <p class="text-sm text-gray-600">Driver Payments</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1">${{ number_format($this->driverPayments, 2) }}</p>
                </div>

                <div class="bg-yellow-50 rounded-lg p-4 border-l-4 border-yellow-500">
                    <p class="text-sm text-gray-600">Maintenance</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1">${{ number_format($this->maintenanceCosts, 2) }}</p>
                </div>

                <div class="bg-rose-50 rounded-lg p-4 border-l-4 border-rose-500">
                    <p class="text-sm text-gray-600">Total Costs</p>
                    <p class="text-2xl font-bold text-rose-600 mt-1">${{ number_format($this->totalCosts, 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Profitability Analysis -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow p-6 border-t-4 {{ $this->netProfit >= 0 ? 'border-green-500' : 'border-red-500' }}">
                <div>
                    <p class="text-sm font-medium text-gray-600">Net Profit</p>
                    <p class="text-3xl font-bold {{ $this->netProfit >= 0 ? 'text-green-600' : 'text-red-600' }} mt-2">${{ number_format($this->netProfit, 2) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-t-4 border-indigo-500">
                <div>
                    <p class="text-sm font-medium text-gray-600">Profit Margin</p>
                    <p class="text-3xl font-bold text-indigo-600 mt-2">{{ number_format($this->profitMarginPercentage, 1) }}%</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-t-4 border-pink-500">
                <div>
                    <p class="text-sm font-medium text-gray-600">Cost Ratio</p>
                    <p class="text-3xl font-bold text-pink-600 mt-2">{{ number_format($this->costRatioPercentage, 1) }}%</p>
                </div>
            </div>
        </div>

        <!-- KM Efficiency -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-900">KM Efficiency Metrics</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-6">
                <div class="bg-cyan-50 rounded-lg p-4 border-l-4 border-cyan-500">
                    <p class="text-sm text-gray-600">Total KM Driven</p>
                    <p class="text-2xl font-bold text-cyan-600 mt-1">{{ number_format($this->totalKmDriven, 1) }} km</p>
                </div>

                <div class="bg-teal-50 rounded-lg p-4 border-l-4 border-teal-500">
                    <p class="text-sm text-gray-600">Avg KM/Trip</p>
                    <p class="text-2xl font-bold text-teal-600 mt-1">{{ number_format($this->averageKmPerTrip, 1) }} km</p>
                </div>

                <div class="bg-sky-50 rounded-lg p-4 border-l-4 border-sky-500">
                    <p class="text-sm text-gray-600">Revenue/KM</p>
                    <p class="text-2xl font-bold text-sky-600 mt-1">${{ number_format($this->revenuePerKm, 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-800">
                <strong>Note:</strong> This dashboard displays financial metrics for <strong>{{ $this->period }}</strong>.
                Metrics are calculated from completed trips and journal entries.
            </p>
        </div>
    </div>
@endsection
