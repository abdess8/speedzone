<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor-defined roles ("Préparateur de commandes", "Gestionnaire de stock").
     *
     * `name` deliberately keeps its global unique index. Custom roles are stored
     * under a generated, namespaced name (vendor.{owner}.{slug}) and display
     * `label` in the UI, so a vendor can never create a role called "Admin" or
     * "Seller" and silently widen the meaning of the dozens of existing
     * where('name', Role::SELLER) lookups across the codebase.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('label')->nullable()->after('name');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('label');
        });
    }
};
