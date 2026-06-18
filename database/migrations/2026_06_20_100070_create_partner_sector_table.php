<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sectors a partner delegates to speedZone within their delegated cities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_sector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['partner_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_sector');
    }
};
