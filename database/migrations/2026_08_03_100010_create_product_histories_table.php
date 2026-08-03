<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field-level audit trail of a product sheet.
     *
     * Same shape as order_change_histories, for the same reason: a price that
     * moved yesterday has to be explainable next month, and "who changed it" is
     * the first question asked.
     */
    public function up(): void
    {
        Schema::create('product_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Null when the change came from an automated process rather than a
            // person, and when the author's account was later removed.
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field_name', 60);
            // Rendered values, not raw ids: the reader wants "Casablanca", and a
            // category renamed later must not rewrite what the log says.
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_histories');
    }
};
