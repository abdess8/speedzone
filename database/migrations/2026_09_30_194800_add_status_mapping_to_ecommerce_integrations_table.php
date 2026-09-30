<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_integrations', function (Blueprint $table) {
            $table->json('status_mapping')->nullable()->after('field_mapping');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_integrations', function (Blueprint $table) {
            $table->dropColumn('status_mapping');
        });
    }
};
