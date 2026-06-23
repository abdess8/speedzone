<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B delivery partners (e.g. "Sendit") that delegate last-mile deliveries to
 * speedZone in cities they do not cover. Each partner owns the API credentials
 * used to pull deliveries from and push status updates back to their platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_url')->nullable();
            $table->string('ice_number')->nullable();
            $table->boolean('is_active')->default(true);

            // Hub city where this partner drops off packages for speedZone.
            $table->foreignId('reception_city_id')->nullable()->constrained('cities')->nullOnDelete();

            // Partner API integration credentials.
            $table->string('api_base_url')->nullable();
            $table->string('client_id')->nullable();
            // Encrypted at rest via the model's "encrypted" cast.
            $table->text('client_secret')->nullable();

            $table->unsignedInteger('sync_frequency_minutes')->default(30);
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('sync_status')->default(false);

            $table->timestamps();

            $table->index('is_active');
            $table->index('reception_city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
