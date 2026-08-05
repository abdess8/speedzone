<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables carrying a store boundary.
     *
     * `returns.store_id` is denormalised (it is derivable through the order) so
     * the global scope can be applied uniformly, without a whereHas on every
     * listing query.
     *
     * `transfers` is deliberately absent: it is an internal dispatch entity a
     * seller never reads directly.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'orders',
        'invoices',
        'pickup_requests',
        'returns',
        'support_tickets',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('store_id')->nullable()
                    ->constrained()->nullOnDelete();
                $blueprint->index(['store_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_store_id_created_at_index');
                $blueprint->dropConstrainedForeignId('store_id');
            });
        }
    }
};
