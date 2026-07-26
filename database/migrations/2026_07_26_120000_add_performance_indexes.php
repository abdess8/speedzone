<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the dashboard and order-list query shapes.
 *
 * The existing indexes are all single column. MySQL can only use one index per
 * table reference, so a query that filters on partner_id and then sorts by
 * created_at has to choose between filtering and sorting, and pays for a
 * filesort either way. The composite indexes below cover both halves.
 */
return new class extends Migration
{
    /**
     * Indexes to create, keyed by table.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const INDEXES = [
        'orders' => [
            // Default list: WHERE partner_id IS NULL ORDER BY created_at DESC.
            // Also covers the pagination COUNT(*).
            'orders_partner_created_idx' => ['partner_id', 'created_at'],
            // Dashboard status breakdowns scoped to a period.
            'orders_partner_status_created_idx' => ['partner_id', 'status', 'created_at'],
            // Revenue and average-delivery-time aggregates.
            // delivered_at had no index at all.
            'orders_status_delivered_idx' => ['status', 'delivered_at'],
            // Seller-scoped and driver-scoped lists, both sorted by date.
            'orders_seller_created_idx' => ['seller_id', 'created_at'],
            'orders_driver_created_idx' => ['driver_id', 'created_at'],
            // "New customers" groups by phone and compares MIN(created_at).
            'orders_phone_created_idx' => ['customer_phone', 'created_at'],
        ],
        'order_status_histories' => [
            // Recent activity feed: ORDER BY created_at DESC LIMIT 20.
            // created_at was not indexed, making this the slowest dashboard query.
            'osh_created_idx' => ['created_at'],
            // Per-order timeline on the order detail screen.
            'osh_order_created_idx' => ['order_id', 'created_at'],
            // Delivery-date filter on the order list.
            'osh_status_created_idx' => ['status', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach ($indexes as $name => $columns) {
                    if (! $this->indexExists($table, $name)) {
                        $blueprint->index($columns, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach (array_keys($indexes) as $name) {
                    if ($this->indexExists($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $existing) => $existing['name'] === $index);
    }
};
