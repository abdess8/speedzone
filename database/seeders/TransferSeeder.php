<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransferSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->orderBy('id')->first();
        $cities = City::query()->active()->orderBy('id')->get();

        if (! $actor || $cities->count() < 2) {
            return;
        }

        $fromCity = City::query()->where('name', 'Casablanca')->first() ?? $cities->first();
        $toCity = City::query()->where('name', 'Rabat')->first() ?? $cities->skip(1)->first();

        $orders = Order::query()
            ->eligibleForTransfer($fromCity->id, $toCity->id)
            ->limit(3)
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $transfer = app(\App\Services\TransferService::class)->create(
            $actor,
            $fromCity->id,
            $toCity->id,
            $orders->pluck('id')->all(),
            'Demo inter-city transfer.'
        );

        $this->command?->info("Created demo transfer {$transfer->reference} with {$orders->count()} orders.");
    }
}
