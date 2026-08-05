<?php

use App\Enums\StockReceptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inbound shipment: the paperwork that travels with stock a vendor sends to
     * one of our depots.
     *
     * The vendor declares what he shipped; the hub declares what it actually
     * counted. Both numbers are kept — the gap between them is the whole point
     * of the document.
     */
    public function up(): void
    {
        Schema::create('stock_receptions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('status')->default(StockReceptionStatus::DRAFT->value);

            // Declared by the vendor.
            $table->date('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('sending_notes')->nullable();

            // Declared by the hub on arrival.
            $table->date('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reception_notes')->nullable();

            // Set once the counted quantities have been credited to the catalog.
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receptions');
    }
};
