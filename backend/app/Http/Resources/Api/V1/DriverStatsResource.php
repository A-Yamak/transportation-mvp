<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Driver Stats Resource
 *
 * Transforms driver statistics data for API responses.
 * Includes today, this_month, all_time, and vehicle stats.
 */
class DriverStatsResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Since we're passing an array directly, not a model
        return [
            'today' => [
                'trips_count' => $this->resource['today']['trips_count'],
                'destinations_completed' => $this->resource['today']['destinations_completed'],
                'km_driven' => $this->resource['today']['km_driven'],
            ],
            'this_month' => [
                'trips_count' => $this->resource['this_month']['trips_count'],
                'destinations_completed' => $this->resource['this_month']['destinations_completed'],
                'km_driven' => $this->resource['this_month']['km_driven'],
            ],
            'all_time' => [
                'trips_count' => $this->resource['all_time']['trips_count'],
                'destinations_completed' => $this->resource['all_time']['destinations_completed'],
                'km_driven' => $this->resource['all_time']['km_driven'],
            ],
            'vehicle' => $this->resource['vehicle'],
        ];
    }
}
