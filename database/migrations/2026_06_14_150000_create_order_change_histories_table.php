<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for order field modifications (customer info, amounts, flags, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_change_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('field_name');
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_change_histories');
    }
};
