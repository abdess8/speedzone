<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderImportService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * Create every order of a validated batch.
     *
     * The whole batch is one transaction: the rows were validated together on
     * screen and again by ImportOrdersRequest, so a failure here is a system
     * error rather than a bad row, and a seller who uploaded four hundred
     * parcels should not have to work out which half of them exist.
     *
     * @param  array<int, array<string, mixed>>  $rows  Validated order payloads.
     * @return Collection<int, Order>
     */
    public function import(array $rows, User $seller): Collection
    {
        return DB::transaction(function () use ($rows, $seller): Collection {
            $orders = collect();

            foreach ($rows as $row) {
                $orders->push($this->orderService->create($row, $seller));
            }

            return $orders;
        });
    }
}
