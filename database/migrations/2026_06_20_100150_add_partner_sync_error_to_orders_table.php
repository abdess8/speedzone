<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the last outbound partner status sync failure on an order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'partner_sync_error')) {
                $table->text('partner_sync_error')->nullable()->after('external_tracking_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'partner_sync_error')) {
                $table->dropColumn('partner_sync_error');
            }
        });
    }
};
