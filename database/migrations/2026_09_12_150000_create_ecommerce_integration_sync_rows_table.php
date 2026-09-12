<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_integration_sync_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ecommerce_integration_id');
            $table->unsignedBigInteger('ecommerce_integration_sync_id');
            $table->string('external_order_id');
            $table->string('ref')->nullable();
            $table->string('status', 32);
            $table->json('values')->nullable();
            $table->json('raw')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->foreign('ecommerce_integration_id', 'ecom_sync_rows_integration_fk')
                ->references('id')
                ->on('ecommerce_integrations')
                ->cascadeOnDelete();
            $table->foreign('ecommerce_integration_sync_id', 'ecom_sync_rows_sync_fk')
                ->references('id')
                ->on('ecommerce_integration_syncs')
                ->cascadeOnDelete();
            $table->unique(
                ['ecommerce_integration_sync_id', 'external_order_id'],
                'ecom_sync_rows_sync_external_unique'
            );
            $table->index(
                ['ecommerce_integration_id', 'status'],
                'ecom_sync_rows_integration_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_integration_sync_rows');
    }
};
