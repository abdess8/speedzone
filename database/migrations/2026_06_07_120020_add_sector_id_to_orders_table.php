<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders are now delivered to a specific sector inside the destination
     * city. The column is nullable so historical orders (created before
     * sectors existed) remain valid; new orders require it at the app level.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('sector_id')
                ->nullable()
                ->after('city_id')
                ->constrained('sectors')
                ->nullOnDelete();

            $table->index('sector_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropIndex(['sector_id']);
            $table->dropColumn('sector_id');
        });
    }
};
