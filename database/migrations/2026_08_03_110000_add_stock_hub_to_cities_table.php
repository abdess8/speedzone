<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which cities hold one of our stock depots.
     *
     * Every city can receive a delivery, but only a handful can warehouse a
     * vendor's goods. The flag is what the vendor picks from when he ships stock
     * in, and what tells us whether a prepared order is already home.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->boolean('is_stock_hub')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('is_stock_hub');
        });
    }
};
