<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliveries were already stamped on orders.delivered_at, but the moment a
     * parcel came back to the seller only lived in the status history. Invoices
     * need both dates side by side, so returns get their own column and the
     * rows already in production are backfilled from that history.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('returned_at')->nullable()->after('delivered_at');
        });

        DB::statement(
            'update orders set returned_at = ('
            .'select max(created_at) from order_status_histories'
            .' where order_status_histories.order_id = orders.id and order_status_histories.status = ?'
            .') where exists ('
            .'select 1 from order_status_histories'
            .' where order_status_histories.order_id = orders.id and order_status_histories.status = ?'
            .')',
            [OrderStatus::RETURNED->value, OrderStatus::RETURNED->value]
        );
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('returned_at');
        });
    }
};
