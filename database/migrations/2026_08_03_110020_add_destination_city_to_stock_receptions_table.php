<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The depot the vendor addressed this shipment to.
     *
     * Nullable because a draft is allowed to be incomplete, and because the
     * shipments declared before hubs existed have no destination to backfill.
     * The rule that it matches the shop's depot lives in the request, not here:
     * a shop may legitimately be moved to another depot once it is empty, and
     * the shipments it received in the old one must keep pointing there.
     */
    public function up(): void
    {
        Schema::table('stock_receptions', function (Blueprint $table) {
            $table->foreignId('destination_city_id')
                ->nullable()
                ->after('status')
                ->constrained('cities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_receptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_city_id');
        });
    }
};
