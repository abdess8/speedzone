<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Human-visible shop order number (YouCan ref, Shopify #1001, …).
     *
     * Distinct from `external_order_id`, which is the platform UUID used to
     * skip a row that was already imported.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ecommerce_order_ref', 100)
                ->nullable()
                ->after('external_order_id');

            $table->index('ecommerce_order_ref');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ecommerce_order_ref']);
            $table->dropColumn('ecommerce_order_ref');
        });
    }
};
