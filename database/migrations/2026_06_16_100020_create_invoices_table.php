<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An invoice settles a batch of delivered/returned orders for a seller.
     * All monetary fields are snapshots captured at generation time.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number')->unique();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->unsignedInteger('total_orders_count')->default(0);

            // Snapshot amounts (frozen at generation time).
            $table->decimal('delivered_amount', 14, 2)->default(0);
            $table->decimal('returned_amount', 14, 2)->default(0);
            $table->decimal('delivery_fees_total', 14, 2)->default(0);
            $table->decimal('return_fees_total', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);

            $table->string('status')->default(InvoiceStatus::GENERATED->value);

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('payment_receipt_attachment')->nullable();
            $table->string('pdf_file')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('seller_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
