<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link an order to the invoice that settles it. invoice_id stays NULL until
     * the order is billed; invoice_status mirrors the invoice lifecycle for fast
     * filtering of "not yet invoiced" orders.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('return_id')
                ->constrained('invoices')->nullOnDelete();
            $table->string('invoice_status')->nullable()->after('invoice_id');

            $table->index('invoice_id');
            $table->index('invoice_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['invoice_status']);
            $table->dropColumn(['invoice_id', 'invoice_status']);
        });
    }
};
