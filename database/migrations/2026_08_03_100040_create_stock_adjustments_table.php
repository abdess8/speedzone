<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable ledger of every stock movement.
     *
     * One table rather than one per origin, because the question people actually
     * ask is "why does this product show 42?", and answering it must not require
     * merging three timelines by hand. `source` says where a line came from and
     * decides which of the optional columns are filled:
     *
     *   MANUAL      a human counted the shelf   → reason required when delta ≠ 0
     *   RECEPTION   an inbound shipment landed  → stock_reception_id
     *   ORDER       stock left for a customer   → order_id
     *
     * Rows are append-only: StockAdjustment blocks updates and deletes, and
     * there is no updated_at column to make that explicit in the schema itself.
     */
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // Null for system movements; kept nullable so removing an employee
            // never erases the trail he left.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('source', 20);
            $table->string('reason', 40)->nullable();
            $table->string('note', 500)->nullable();

            $table->integer('stock_before');
            $table->integer('stock_after');
            // Redundant with the two columns above on purpose: every stock report
            // groups on it, and recomputing a difference in SQL on a million rows
            // to answer "how much did we lose to theft" is wasteful.
            $table->integer('delta');

            $table->foreignId('stock_reception_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['store_id', 'created_at']);
            $table->index(['store_id', 'source']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
