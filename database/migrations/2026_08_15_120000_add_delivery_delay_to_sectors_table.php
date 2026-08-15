<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The promised delivery window for a sector, e.g. "24H" or "48H-72H".
 *
 * Free text rather than an enum or a number of hours: the commercial grid states
 * ranges, and the value is quoted to the seller as-is instead of being computed
 * against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (! Schema::hasColumn('sectors', 'delivery_delay')) {
                $table->string('delivery_delay', 40)->nullable()->after('delivery_driver_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (Schema::hasColumn('sectors', 'delivery_delay')) {
                $table->dropColumn('delivery_delay');
            }
        });
    }
};
