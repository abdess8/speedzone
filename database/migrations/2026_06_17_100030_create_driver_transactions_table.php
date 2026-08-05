<?php

use App\Enums\DriverTransactionStatus;
use App\Enums\DriverTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver financial transactions. A DELIVERY_PAYMENT row is created automatically
 * when an order is delivered, carrying a snapshot of the sector driver price so
 * the amount is frozen forever. Adjustments / bonuses / penalties may be added
 * manually by an admin. A transaction belongs to at most one driver invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('driver_invoice_id')->nullable()->constrained('driver_invoices')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('driver_price_snapshot', 12, 2)->default(0);
            $table->string('transaction_type')->default(DriverTransactionType::DELIVERY_PAYMENT->value);
            $table->string('status')->default(DriverTransactionStatus::PENDING->value);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('driver_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('transaction_type');
            $table->index('driver_invoice_id');

            // A delivered order generates a single delivery payment.
            $table->unique(['order_id', 'transaction_type'], 'driver_tx_order_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_transactions');
    }
};
