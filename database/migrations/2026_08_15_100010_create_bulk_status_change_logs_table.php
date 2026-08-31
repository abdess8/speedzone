<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit of bulk status edits, one row per *attempted* item.
     *
     * The status timelines (`order_status_histories`, `return_status_histories`)
     * remain the record of what happened to a parcel, and a successful batch
     * still writes to them through the transition services. They cannot answer
     * the question this table exists for: what an operator tried to do, on which
     * parcels at once, and why some of them were refused.
     */
    public function up(): void
    {
        Schema::create('bulk_status_change_logs', function (Blueprint $table) {
            $table->id();
            // Groups the items of one submission, so a batch can be read back
            // as the single operational act it was.
            $table->uuid('batch_id')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 16)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            // Kept alongside the id: a scan that resolves to nothing has no id,
            // and a deleted parcel must still be identifiable in the audit.
            $table->string('reference')->nullable()->index();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->boolean('successful')->index();
            $table->string('failure_reason')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->string('source', 32)->default('BULK_EDIT');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_status_change_logs');
    }
};
