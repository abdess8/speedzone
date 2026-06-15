<?php

namespace App\Services;

use App\Models\Transfer;

class TransferReferenceGenerator
{
    /**
     * Generate a unique transfer reference, e.g. TRF-2026-583920.
     */
    public function generate(): string
    {
        $prefix = strtoupper((string) config('transfer.reference_prefix', 'TRF'));
        $year = (int) date('Y');
        $digits = max(4, (int) config('transfer.reference_random_digits', 6));

        $min = (int) str_pad('1', $digits, '0');
        $max = (int) str_repeat('9', $digits);

        do {
            $random = random_int($min, $max);
            $candidate = sprintf('%s-%d-%d', $prefix, $year, $random);
        } while (Transfer::query()->where('reference', $candidate)->exists());

        return $candidate;
    }
}
