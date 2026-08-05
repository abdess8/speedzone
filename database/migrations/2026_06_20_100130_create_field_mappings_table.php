<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('speedzone_field', 50);
            $table->string('partner_field', 100);
            $table->timestamps();

            $table->unique(['partner_id', 'speedzone_field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_mappings');
    }
};
