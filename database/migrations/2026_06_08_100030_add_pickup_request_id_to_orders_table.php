<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('pickup_request_id')
                ->nullable()
                ->after('seller_id')
                ->constrained('pickup_requests')
                ->nullOnDelete();

            $table->index('pickup_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pickup_request_id']);
            $table->dropIndex(['pickup_request_id']);
            $table->dropColumn('pickup_request_id');
        });
    }
};
