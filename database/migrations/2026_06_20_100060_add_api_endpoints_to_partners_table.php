<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-partner API endpoint paths and the partner status used when pulling
 * deliveries during ingestion (e.g. "DISTRIBUTED" for Sendit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('endpoint_statuses')->default('/all-status-deliveries')->after('client_secret');
            $table->string('endpoint_deliveries')->default('/deliveries')->after('endpoint_statuses');
            $table->string('endpoint_update')->default('/update-deliveries')->after('endpoint_deliveries');
            // Partner status filter used when ingesting (maps to our IN_TRANSIT via status_mappings).
            $table->string('ingestion_partner_status')->nullable()->after('endpoint_update');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'endpoint_statuses',
                'endpoint_deliveries',
                'endpoint_update',
                'ingestion_partner_status',
            ]);
        });
    }
};
