<?php

namespace App\Services;

use App\Models\Order;

class TrackingNumberGenerator
{
    /**
     * Generate a unique tracking number, e.g. SPD-2026-583920.
     *
     * The tracking number doubles as the order number.
     */
    public function generate(): string
    {
        $code = strtoupper((string) config('orders.company_code', 'SPD'));
        $year = (int) date('Y');
        $digits = (int) config('orders.tracking_random_digits', 6);

        $min = (int) str_pad('1', $digits, '0');
        $max = (int) str_repeat('9', $digits);

        // Retry loop guards against the (rare) random collision under concurrency.
        do {
            $random = random_int($min, $max);
            $candidate = sprintf('%s-%d-%d', $code, $year, $random);
        } while ($this->exists($candidate));

        return $candidate;
    }

    /**
     * The store boundary is lifted on purpose: the unique index is global, so a
     * number already carried by another shop's parcel is taken here too. Asking
     * the scoped query would let the candidate through and turn the collision the
     * loop above exists to absorb into a failed insert.
     */
    private function exists(string $trackingNumber): bool
    {
        return Order::acrossStores()
            ->where('tracking_number', $trackingNumber)
            ->exists();
    }
}
