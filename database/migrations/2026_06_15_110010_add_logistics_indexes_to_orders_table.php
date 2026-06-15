<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingIndexes = collect(DB::select('SHOW INDEX FROM orders'))
            ->pluck('Key_name')
            ->unique();

        Schema::table('orders', function (Blueprint $table) use ($existingIndexes) {
            if (! $existingIndexes->contains('orders_status_index')) {
                $table->index('status');
            }

            if (! $existingIndexes->contains('orders_seller_id_index')) {
                $table->index('seller_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['seller_id']);
        });
    }
};
