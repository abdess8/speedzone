<?php

namespace App\Services;

use App\Models\PickupRequest;

class PickupReferenceGenerator
{
    /**
     * Generate a sequential pickup reference, e.g. PU-2026-000001.
     */
    public function generate(): string
    {
        $prefix = strtoupper((string) config('pickup.reference_prefix', 'PU'));
        $year = (int) date('Y');
        $digits = max(4, (int) config('pickup.reference_sequence_digits', 6));

        $latest = PickupRequest::query()
            ->where('reference', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;

        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT));
            $sequence++;
        } while (PickupRequest::query()->where('reference', $candidate)->exists());

        return $candidate;
    }
}
