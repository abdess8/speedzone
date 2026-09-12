<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per YouCan (or later Shopify) pull, so the seller can open
     * "24 orders added" and see exactly that run.
     */
    public function up(): void
    {
        Schema::create('ecommerce_integration_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_integration_id')
                ->constrained('ecommerce_integrations')
                ->cascadeOnDelete();
            $table->string('status', 32)->default('running');
            $table->string('trigger', 32);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('skipped_reasons')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ecommerce_integration_id', 'status'], 'ecom_syncs_integration_status_index');
            $table->index(['ecommerce_integration_id', 'started_at'], 'ecom_syncs_integration_started_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('ecommerce_sync_id')
                ->nullable()
                ->after('external_order_id')
                ->constrained('ecommerce_integration_syncs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ecommerce_sync_id');
        });

        Schema::dropIfExists('ecommerce_integration_syncs');
    }
};
