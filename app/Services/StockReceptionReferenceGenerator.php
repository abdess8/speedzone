<?php

namespace App\Services;

use App\Models\StockReception;

class StockReceptionReferenceGenerator
{
    /**
     * Generate a unique inbound shipment reference, e.g. RCP-2026-583920.
     *
     * Printed on the parcel and read back at the depot, so it follows the same
     * shape as the tracking, transfer and return references operators already
     * type from paper.
     */
    public function generate(): string
    {
        $prefix = strtoupper((string) config('stock.reference_prefix', 'RCP'));
        $year = (int) date('Y');
        $digits = max(4, (int) config('stock.reference_random_digits', 6));

        $min = (int) str_pad('1', $digits, '0');
        $max = (int) str_repeat('9', $digits);

        do {
            $random = random_int($min, $max);
            $candidate = sprintf('%s-%d-%d', $prefix, $year, $random);
        } while ($this->exists($candidate));

        return $candidate;
    }

    /**
     * Uniqueness is global, so the store boundary has to be lifted: a vendor
     * must not be handed a reference another vendor already carries.
     */
    private function exists(string $reference): bool
    {
        return StockReception::acrossStores()->where('reference', $reference)->exists();
    }
}
