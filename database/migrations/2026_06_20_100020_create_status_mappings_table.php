<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-partner translation table between speedZone order statuses and the
 * partner's own status vocabulary. Used both for ingestion (partner -> us)
 * and outbound pushes (us -> partner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            // speedZone status value (matches App\Enums\OrderStatus values).
            $table->string('speedzone_status');
            // The partner's equivalent status string (e.g. "DISTRIBUTED").
            $table->string('partner_status');
            $table->timestamps();

            $table->unique(['partner_id', 'speedzone_status']);
            $table->index(['partner_id', 'partner_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_mappings');
    }
};
