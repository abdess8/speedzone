<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The depot a stock order ships out of.
     *
     * Snapshotted from the shop at creation rather than read through the
     * relation: an order already in flight must keep leaving from the depot it
     * was picked in, even if the shop later moves its stock elsewhere.
     *
     * Null for the parcel-only flow, where the goods start at the vendor's door
     * and the origin is his own city.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('stock_hub_city_id')
                ->nullable()
                ->after('sector_id')
                ->constrained('cities')
                ->nullOnDelete();

            // Drives the preparation queue and the transfer eligibility list.
            $table->index(['stock_hub_city_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['stock_hub_city_id', 'status']);
            $table->dropConstrainedForeignId('stock_hub_city_id');
        });
    }
};
