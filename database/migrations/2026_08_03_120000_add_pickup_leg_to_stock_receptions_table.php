<?php

use App\Enums\StockReceptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Insert the collection leg between the vendor's declaration and the depot count.
 *
 * `SENT` used to mean "the vendor handed it over and the depot may count it",
 * which collapsed two distinct moments into one. It now means "waiting for a
 * collector", and is renamed to say so — a status called SENT sitting before
 * IN_TRANSIT reads as a contradiction to whoever comes next.
 */
return new class extends Migration
{
    /** The value the status column held before the collection leg existed. */
    private const LEGACY_AWAITING = 'SENT';

    public function up(): void
    {
        Schema::table('stock_receptions', function (Blueprint $table) {
            // Who went to the shop, when, and what he had to say about it. Kept on
            // the document rather than read back from the status journal so the
            // list screen can show and filter on the collector without a join per
            // row.
            $table->foreignId('collected_by')
                ->nullable()
                ->after('sending_notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('collected_at')->nullable()->after('collected_by');
            $table->text('collection_notes')->nullable()->after('collected_at');
            $table->timestamp('dispatched_at')->nullable()->after('collection_notes');

            // The pickup queue is worked per city, and a collector filters it by
            // the depot he drives to.
            $table->index(['status', 'destination_city_id']);
        });

        DB::table('stock_receptions')
            ->where('status', self::LEGACY_AWAITING)
            ->update(['status' => StockReceptionStatus::AWAITING_PICKUP->value]);
    }

    public function down(): void
    {
        // Everything the collection leg produced folds back into the single state
        // that preceded it: a parcel already on the road is, for the old flow,
        // simply one the depot has not counted yet.
        DB::table('stock_receptions')
            ->whereIn('status', [
                StockReceptionStatus::AWAITING_PICKUP->value,
                StockReceptionStatus::COLLECTED->value,
                StockReceptionStatus::IN_TRANSIT->value,
            ])
            ->update(['status' => self::LEGACY_AWAITING]);

        Schema::table('stock_receptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'destination_city_id']);
            $table->dropConstrainedForeignId('collected_by');
            $table->dropColumn(['collected_at', 'collection_notes', 'dispatched_at']);
        });
    }
};
