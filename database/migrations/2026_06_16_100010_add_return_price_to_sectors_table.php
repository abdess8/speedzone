<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Return fee charged back to the seller when an order is returned. Lives on
     * the sector alongside delivery_price; it is snapshotted onto invoices at
     * generation time so price changes never alter historical invoices.
     */
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (! Schema::hasColumn('sectors', 'return_price')) {
                $table->decimal('return_price', 12, 2)->default(0)->after('delivery_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (Schema::hasColumn('sectors', 'return_price')) {
                $table->dropColumn('return_price');
            }
        });
    }
};
