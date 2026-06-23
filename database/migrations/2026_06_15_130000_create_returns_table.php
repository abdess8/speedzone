<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('initiated_by_role');
            $table->string('reason');
            $table->string('status')->default('CREATED');
            $table->foreignId('current_location_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->text('return_address')->nullable();
            $table->text('return_notes')->nullable();
            $table->string('updated_customer_name')->nullable();
            $table->string('updated_customer_phone')->nullable();
            $table->text('updated_address')->nullable();
            $table->foreignId('updated_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('initiated_by_role');
            $table->index('reason');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
