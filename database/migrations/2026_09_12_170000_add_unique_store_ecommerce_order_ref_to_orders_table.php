<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A SpeedZone shop must not hold two parcels for the same storefront
     * order number (YouCan ref, Shopify #1001, …).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unique(
                ['store_id', 'ecommerce_order_ref'],
                'orders_store_ecommerce_ref_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_store_ecommerce_ref_unique');
        });
    }
};
