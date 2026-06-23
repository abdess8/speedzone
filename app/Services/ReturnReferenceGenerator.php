<?php

namespace App\Services;

use App\Models\OrderReturn;

class ReturnReferenceGenerator
{
    /**
     * Generate a unique return reference, e.g. RTN-2026-583920.
     */
    public function generate(): string
    {
        $prefix = strtoupper((string) config('returns.reference_prefix', 'RTN'));
        $year = (int) date('Y');
        $digits = max(4, (int) config('returns.reference_random_digits', 6));

        $min = (int) str_pad('1', $digits, '0');
        $max = (int) str_repeat('9', $digits);

        do {
            $random = random_int($min, $max);
            $candidate = sprintf('%s-%d-%d', $prefix, $year, $random);
        } while (OrderReturn::query()->where('reference', $candidate)->exists());

        return $candidate;
    }
}
