<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A delivery that misses the customer no longer ends the order, so the
     * number of times the driver has knocked has to be carried on the row: it
     * is what tells an operator "this one has been out three times" without
     * walking the status history.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedSmallInteger('failed_attempts_count')
                ->default(0)
                ->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('failed_attempts_count');
        });
    }
};
