<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each sector carries its own driver payout rate. The amount a driver earns for
 * a delivery is snapshotted from this value at the moment an order is delivered,
 * so later rate changes never affect already-earned transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (! Schema::hasColumn('sectors', 'delivery_driver_price')) {
                $table->decimal('delivery_driver_price', 12, 2)->default(0)->after('return_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (Schema::hasColumn('sectors', 'delivery_driver_price')) {
                $table->dropColumn('delivery_driver_price');
            }
        });
    }
};
