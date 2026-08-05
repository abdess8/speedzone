<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reception_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_reception_id')->constrained()->cascadeOnDelete();
            // Restricted, not cascading: a line is accounting evidence for the
            // quantity that entered the depot.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity_sent');
            // Null until the hub counts the parcel; zero means "counted, nothing
            // usable inside", which is a very different statement.
            $table->unsignedInteger('quantity_received')->nullable();
            $table->unsignedInteger('quantity_rejected')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // One line per product: a second row for the same reference would
            // make "how many did we receive" ambiguous.
            $table->unique(['stock_reception_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reception_items');
    }
};
