<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link table between a driver invoice and the transactions it settles, with a
 * frozen amount snapshot for each line. A transaction can belong to a single
 * invoice (enforced by the unique constraint on driver_transaction_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_invoice_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_invoice_id')->constrained('driver_invoices')->cascadeOnDelete();
            $table->foreignId('driver_transaction_id')->constrained('driver_transactions')->cascadeOnDelete();
            $table->decimal('amount_snapshot', 12, 2)->default(0);
            $table->timestamps();

            $table->unique('driver_transaction_id');
            $table->index('driver_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_invoice_transactions');
    }
};
