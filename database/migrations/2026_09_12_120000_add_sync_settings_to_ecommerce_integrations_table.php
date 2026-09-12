<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_integrations', function (Blueprint $table) {
            $table->boolean('auto_sync_enabled')->default(false)->after('connected_by');
            $table->unsignedSmallInteger('sync_interval_minutes')->default(15)->after('auto_sync_enabled');
            $table->string('import_status', 64)->default('open')->after('sync_interval_minutes');
            $table->timestamp('last_synced_at')->nullable()->after('import_status');
            $table->timestamp('next_sync_at')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'auto_sync_enabled',
                'sync_interval_minutes',
                'import_status',
                'last_synced_at',
                'next_sync_at',
            ]);
        });
    }
};
