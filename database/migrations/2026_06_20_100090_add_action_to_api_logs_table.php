<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguish authentication (login/token) calls from regular API consumption.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->string('action', 20)->default('API')->after('method');
            $table->unsignedInteger('duration_ms')->nullable()->after('status_code');

            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropColumn(['action', 'duration_ms']);
        });
    }
};
