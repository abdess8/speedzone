<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('city')->nullable()->after('last_name');
            $table->text('address')->nullable()->after('city');
            $table->string('phone_number')->nullable()->after('address');
            $table->string('cin')->nullable()->after('phone_number');
            $table->string('ice_number')->nullable()->after('cin');
            $table->string('photo')->nullable()->after('ice_number');
            $table->json('attached_files')->nullable()->after('photo');

            $table->foreignId('role_id')
                ->nullable()
                ->after('id')
                ->constrained('roles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn([
                'first_name',
                'last_name',
                'city',
                'address',
                'phone_number',
                'cin',
                'ice_number',
                'photo',
                'attached_files',
            ]);
        });
    }
};
