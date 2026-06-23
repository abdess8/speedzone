<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cities a partner delegates to speedZone. We only ingest deliveries whose
 * destination district matches one of these cities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_city', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['partner_id', 'city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_city');
    }
};
