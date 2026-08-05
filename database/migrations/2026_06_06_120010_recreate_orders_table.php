<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the legacy generic "orders" table with the logistics schema.
     *
     * The original table (reference/description/metadata) is dropped because it
     * never carried production data and does not match the delivery model.
     */
    public function up(): void
    {
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Tracking number doubles as the order number (e.g. SPD-2026-583920).
            $table->string('tracking_number')->unique();

            // The seller is the authenticated user who created the order.
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            // Customer information.
            $table->string('customer_first_name');
            $table->string('customer_last_name');
            $table->string('customer_phone');
            $table->text('customer_address');

            // Delivery destination (managed dynamically from the admin module).
            $table->foreignId('city_id')->constrained('cities')->restrictOnDelete();

            // Payment information.
            $table->string('payment_method')->default(PaymentMethod::CASH->value);

            // Financial information.
            $table->decimal('order_amount', 12, 2)->default(0);
            $table->decimal('delivery_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            // Package information.
            $table->text('notes')->nullable();
            $table->boolean('is_fragile')->default(false);
            $table->boolean('can_be_opened')->default(false);

            // Lifecycle status.
            $table->string('status')->default(OrderStatus::CREATED->value);

            $table->timestamps();

            $table->index('seller_id');
            $table->index('city_id');
            $table->index('status');
            $table->index('customer_phone');
            $table->index('payment_method');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
