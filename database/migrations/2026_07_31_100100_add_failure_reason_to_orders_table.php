<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('failure_reason', 40)->nullable()->after('status');
            $table->string('failure_note', 500)->nullable()->after('failure_reason');
            $table->timestamp('failed_at')->nullable()->after('failure_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['failure_reason', 'failure_note', 'failed_at']);
        });
    }
};
