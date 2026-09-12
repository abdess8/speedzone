<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * YouCan seller login uses an email, not an OAuth client id.
     */
    public function up(): void
    {
        Schema::table('ecommerce_integrations', function (Blueprint $table) {
            $table->string('email')->nullable()->after('external_store_id');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_integrations', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
