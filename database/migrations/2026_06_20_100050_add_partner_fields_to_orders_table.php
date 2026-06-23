<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link orders ingested from a B2B partner back to that partner and to the
 * partner's own tracking reference (the "code" returned by their API).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'partner_id')) {
                $table->foreignId('partner_id')
                    ->nullable()
                    ->after('seller_id')
                    ->constrained('partners')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'external_tracking_code')) {
                $table->string('external_tracking_code')->nullable()->after('tracking_number');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('partner_id');
            $table->index('external_tracking_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'partner_id')) {
                $table->dropConstrainedForeignId('partner_id');
            }

            if (Schema::hasColumn('orders', 'external_tracking_code')) {
                $table->dropColumn('external_tracking_code');
            }
        });
    }
};
