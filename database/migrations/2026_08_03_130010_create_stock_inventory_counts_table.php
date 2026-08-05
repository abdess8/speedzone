<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every inventory confirmation, whether or not it moved the stock.
     *
     * The ledger deliberately journals nothing when a count matches the screen:
     * a movement that did not happen has no place among the ones that did. But
     * "nobody has counted this reference since March" and "three people counted
     * it last week and all three found 42" are different situations, and the
     * ledger cannot tell them apart. This table can: it records the act of
     * counting rather than its effect.
     *
     * That is also why it carries the machine and the position. When a shelf is
     * short and two employees each say they counted it, the argument is settled
     * by which terminal was used and where it stood — not by who speaks louder.
     *
     * Rows are append-only, like the ledger they sit beside.
     */
    public function up(): void
    {
        Schema::create('stock_inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // Kept nullable so removing an employee never erases the trail he left.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Set only when the count differed and the ledger recorded a movement,
            // so the sheet links straight to the correction it caused.
            $table->foreignId('stock_adjustment_id')->nullable()->constrained()->nullOnDelete();

            $table->integer('counted_quantity');
            $table->integer('stock_before');
            $table->integer('delta');

            // IPv6 needs 45 characters.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            // Human-readable summary of the user agent ("Chrome on Windows"), so a
            // dispute does not start by parsing a 300-character string.
            $table->string('device_label', 120)->nullable();

            // Volunteered by the browser and therefore advisory: it is a corroborating
            // detail, never a proof. Declared wide enough for full GPS precision.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('location_accuracy_m')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['store_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_inventory_counts');
    }
};
