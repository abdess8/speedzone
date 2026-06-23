<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot linking drivers (users) to the sectors they serve. A driver can
     * cover many sectors and a sector can be served by many drivers.
     */
    public function up(): void
    {
        Schema::create('driver_sector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sector_id']);
            $table->index('user_id');
            $table->index('sector_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_sector');
    }
};
