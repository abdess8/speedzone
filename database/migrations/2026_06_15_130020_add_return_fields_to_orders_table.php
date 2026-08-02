<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('return_id')->nullable()->after('pickup_request_id')->constrained('returns')->nullOnDelete();
            $table->boolean('is_returned')->default(false)->after('return_id');

            $table->index('return_id');
            $table->index('is_returned');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['return_id']);
            $table->dropColumn(['return_id', 'is_returned']);
        });
    }
};
