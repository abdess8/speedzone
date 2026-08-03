<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // seller_id is the vendor account (never a team member): a catalog
            // keyed in by an employee belongs to his employer. store_id is the
            // shop the row is filed under, enforced by BelongsToStore.
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('name');
            $table->string('sku', 64);
            $table->string('barcode', 64)->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();

            $table->decimal('unit_price', 12, 2)->default(0);
            // Purchase cost is what makes a margin computable; sellers who do not
            // track it leave it empty rather than declaring a fake zero.
            $table->decimal('cost_price', 12, 2)->nullable();

            $table->boolean('is_fragile')->default(false);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();

            // Denormalised availability. Every write goes through StockLedger,
            // which locks the row and journals the movement in the same
            // transaction, so this column and stock_adjustments cannot diverge.
            $table->integer('stock_quantity')->default(0);

            $table->boolean('is_active')->default(true);
            // Hub-side quarantine (stock.admin_override): a blocked product stays
            // visible and countable but can no longer be picked into an order.
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('blocked_reason', 255)->nullable();

            $table->timestamps();
            // Orders keep a foreign key on their product line, so a deletion is
            // always logical.
            $table->softDeletes();

            // References are unique inside a shop, not across the platform: two
            // vendors both selling "TSHIRT-M" is normal.
            $table->unique(['store_id', 'sku']);
            $table->unique(['store_id', 'barcode']);
            $table->index(['store_id', 'is_active']);
            $table->index(['seller_id', 'is_active']);
            $table->index(['store_id', 'category']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
