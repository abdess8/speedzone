<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The depot that holds this shop's stock.
     *
     * Distinct from `city_id`, which is where the vendor himself sits: a
     * Marrakech shop may perfectly well warehouse in Casablanca. One depot per
     * shop, so this single column answers "where is my stock?" without a join.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('stock_hub_city_id')
                ->nullable()
                ->after('city_id')
                ->constrained('cities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_hub_city_id');
        });
    }
};
