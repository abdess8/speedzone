<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_integrations', function (Blueprint $table) {
            $table->boolean('delivery_included')->default(true)->after('import_status');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_integrations', function (Blueprint $table) {
            $table->dropColumn('delivery_included');
        });
    }
};
