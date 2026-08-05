<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Line items linking an invoice to the orders it settles. Each row stores a
     * snapshot of the order's amounts so the invoice never changes afterwards.
     */
    public function up(): void
    {
        Schema::create('invoice_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->decimal('order_amount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('return_fee', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);

            $table->string('order_status_at_invoice');

            $table->timestamps();

            // An order can only ever be billed once.
            $table->unique('order_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_orders');
    }
};
