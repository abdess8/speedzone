<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When enabled, outbound status updates for partner deliveries are pushed
 * back to the partner API after a local status change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('partners', 'sync_status')) {
            return;
        }

        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('sync_status')->default(false)->after('last_synced_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('partners', 'sync_status')) {
            return;
        }

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('sync_status');
        });
    }
};
