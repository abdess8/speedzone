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

        // Across every shop, both here and in the collision check below: the
        // reference is sequential behind a global unique index, so reading only
        // the active store would restart the numbering at 1 for the second vendor
        // and collide with the first one's references on every insert.
        $latest = PickupRequest::acrossStores()
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
        } while (PickupRequest::acrossStores()->where('reference', $candidate)->exists());

        return $candidate;
    }
}
