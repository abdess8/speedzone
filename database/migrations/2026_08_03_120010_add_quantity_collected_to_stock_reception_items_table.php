<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the collector actually took away, line by line.
 *
 * Nullable and never backfilled: a line collected before this column existed was
 * genuinely never counted at the shop, and writing the declared figure into it
 * would invent a verification that never happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reception_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity_collected')->nullable()->after('quantity_sent');
        });
    }

    public function down(): void
    {
        Schema::table('stock_reception_items', function (Blueprint $table) {
            $table->dropColumn('quantity_collected');
        });
    }
};
