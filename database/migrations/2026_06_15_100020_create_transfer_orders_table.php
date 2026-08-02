<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('order_id');
            $table->index('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_orders');
    }
};
