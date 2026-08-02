<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->foreignId('return_id')->nullable()->after('transfer_id')->constrained('returns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropForeign(['return_id']);
            $table->dropColumn('return_id');
        });
    }
};
