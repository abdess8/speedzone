<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks an order whose advertised price already covers the shipping.
     *
     * Existing orders keep the historical behaviour — the customer was charged
     * the delivery on top — so the column defaults to false rather than being
     * back-filled from the amounts, which would rewrite settled totals.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('delivery_included')->default(false)->after('delivery_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_included');
        });
    }
};
