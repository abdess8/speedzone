<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial audit trail for everything that happens to a driver's money:
 * invoice creation, payment, cancellation, manual adjustments, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_finance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_invoice_id')->nullable()->constrained('driver_invoices')->nullOnDelete();
            $table->string('action');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->index('driver_id');
            $table->index('driver_invoice_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_finance_logs');
    }
};
