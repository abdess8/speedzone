<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each invoice line already snapshots the amounts and the status the order
     * had when it was billed; it now also snapshots the day the parcel was
     * delivered or returned, so a seller reading an old invoice can trace a
     * line back to a date even if the order is edited afterwards.
     */
    public function up(): void
    {
        Schema::table('invoice_orders', function (Blueprint $table): void {
            $table->timestamp('order_completed_at')->nullable()->after('order_status_at_invoice');
        });

        DB::statement(
            'update invoice_orders'
            .' join orders on orders.id = invoice_orders.order_id'
            .' set invoice_orders.order_completed_at = case'
            .' when invoice_orders.order_status_at_invoice = ? then orders.returned_at'
            .' else orders.delivered_at end',
            [OrderStatus::RETURNED->value]
        );
    }

    public function down(): void
    {
        Schema::table('invoice_orders', function (Blueprint $table): void {
            $table->dropColumn('order_completed_at');
        });
    }
};
