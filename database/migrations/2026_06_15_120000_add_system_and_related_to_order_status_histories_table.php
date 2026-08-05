<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('user_id');
            $table->foreignId('pickup_request_id')->nullable()->after('comment')->constrained('pickup_requests')->nullOnDelete();
            $table->foreignId('transfer_id')->nullable()->after('pickup_request_id')->constrained('transfers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfer_id');
            $table->dropConstrainedForeignId('pickup_request_id');
            $table->dropColumn('is_system');
        });
    }
};
