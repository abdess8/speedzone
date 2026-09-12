<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a parcel was born, plus the YouCan (and later Shopify) identity that
     * lets a sync skip a row it already imported.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('creation_source', 32)->default('manual')->after('store_id');
            $table->foreignId('ecommerce_integration_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('ecommerce_integrations')
                ->nullOnDelete();
            $table->string('external_order_id')->nullable()->after('ecommerce_integration_id');

            $table->index('creation_source');
            $table->unique(
                ['ecommerce_integration_id', 'external_order_id'],
                'orders_ecommerce_external_unique'
            );
        });

        DB::table('orders')
            ->whereNotNull('partner_id')
            ->update(['creation_source' => 'partner']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_ecommerce_external_unique');
            $table->dropConstrainedForeignId('ecommerce_integration_id');
            $table->dropColumn(['creation_source', 'external_order_id']);
        });
    }
};
