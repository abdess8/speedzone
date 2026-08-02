<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Template for the store created automatically with the account.
            $table->string('default_store_name')->nullable()->after('ice_number');
            $table->string('default_store_logo')->nullable()->after('default_store_name');

            // Null for a vendor admin, the vendor's id for each of his team
            // members. Drives User::accountOwnerId(), which every `.own` scope
            // now resolves through.
            $table->foreignId('parent_user_id')->nullable()->after('role_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('parent_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['parent_user_id']);
            $table->dropConstrainedForeignId('parent_user_id');
            $table->dropColumn(['default_store_name', 'default_store_logo']);
        });
    }
};
