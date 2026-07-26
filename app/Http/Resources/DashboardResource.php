<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'] ?? [],
            'charts' => $this->resource['charts'] ?? [],
            'recentOrders' => $this->resource['recentOrders'] ?? [],
            'recentActivities' => $this->resource['recentActivities'] ?? [],
            'topCustomers' => $this->resource['topCustomers'] ?? [],
            'topCities' => $this->resource['topCities'] ?? [],
            'topSellers' => $this->resource['topSellers'] ?? [],
            'paymentMethods' => $this->resource['paymentMethods'] ?? [],
            'deliveryPerformance' => $this->resource['deliveryPerformance'] ?? [],
            'meta' => $this->resource['meta'] ?? [],
            'limitations' => $this->resource['limitations'] ?? [],
        ];
    }
}
