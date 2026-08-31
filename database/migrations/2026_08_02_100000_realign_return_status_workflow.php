<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Realign the stored return statuses with the six-step reverse logistics
     * workflow.
     *
     * The old vocabulary described the leg ("to depot", "to seller"); the new
     * one names the place the parcel has reached, and inserts the hub drop-off
     * that used to be implicit. Existing rows map one-to-one:
     *
     *   RECEIVED_AT_DEPOT    → ARRIVED_VENDOR_HUB
     *   IN_TRANSIT_TO_SELLER → IN_DELIVERY_TO_VENDOR
     *   DELIVERED_TO_SELLER  → DELIVERED_TO_VENDOR
     *
     * CREATED and IN_TRANSIT_TO_DEPOT keep their value. RECEIVED_AT_HUB is new
     * and no historic row can carry it, so nothing is backfilled: returns
     * already in flight simply skip a step they never went through.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'RECEIVED_AT_DEPOT' => 'ARRIVED_VENDOR_HUB',
        'IN_TRANSIT_TO_SELLER' => 'IN_DELIVERY_TO_VENDOR',
        'DELIVERED_TO_SELLER' => 'DELIVERED_TO_VENDOR',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rename(array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table('returns')->where('status', $from)->update(['status' => $to]);

            DB::table('return_status_histories')
                ->where('old_status', $from)
                ->update(['old_status' => $to]);

            DB::table('return_status_histories')
                ->where('new_status', $from)
                ->update(['new_status' => $to]);
        }
    }
};
