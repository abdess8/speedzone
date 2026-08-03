<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-out switch for the "a shop is waiting for a collection" alert.
 *
 * Defaults on, like every other type: a collector who never hears about the round
 * cannot drive it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('stock_pickup_requested')->default(true)->after('return_requested');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('stock_pickup_requested');
        });
    }
};
