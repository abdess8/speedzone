<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who moved the shipment, from where to where, when, and why.
 *
 * A shipment now passes through four pairs of hands before it credits anybody's
 * catalog, so "the vendor says he sent thirty and we recorded twenty-six" has to
 * be answerable without guesswork. Shaped like the pickup, transfer and return
 * journals: old and new status side by side, the actor, a free comment.
 *
 * Append-only, hence no `updated_at`: a journal entry that can be edited answers
 * nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reception_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_reception_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['stock_reception_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reception_status_histories');
    }
};
