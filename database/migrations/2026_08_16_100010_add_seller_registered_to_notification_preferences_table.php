<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-out switch for the "a new shop has signed up" alert.
 *
 * It used to travel as a generic system notification, which made it impossible
 * to address separately from every other announcement. It is now a topic of its
 * own, gated by `notifications.seller_registered`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('seller_registered')->default(true)->after('stock_pickup_requested');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('seller_registered');
        });
    }
};
