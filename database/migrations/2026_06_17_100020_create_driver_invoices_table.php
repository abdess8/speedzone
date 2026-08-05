<?php

use App\Enums\DriverInvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver settlement invoices. Each invoice aggregates the driver's confirmed,
 * not-yet-invoiced transactions over a period. The monetary totals are
 * snapshots and must never be recomputed once the invoice is generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('deliveries_count')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status')->default(DriverInvoiceStatus::GENERATED->value);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_receipt_attachment')->nullable();
            $table->string('pdf_file')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('driver_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_invoices');
    }
};
