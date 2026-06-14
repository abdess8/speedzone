<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('order_value', 12, 2)->nullable()->after('payment_method');
        });

        DB::table('orders')->update([
            'order_value' => DB::raw('order_amount'),
        ]);

        DB::statement('ALTER TABLE orders MODIFY order_amount DECIMAL(12,2) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::table('orders')
            ->whereNull('order_amount')
            ->update(['order_amount' => 0]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_value');
        });

        DB::statement('ALTER TABLE orders MODIFY order_amount DECIMAL(12,2) NOT NULL DEFAULT 0');
    }
};
