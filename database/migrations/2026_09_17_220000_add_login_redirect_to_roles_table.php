<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Screen a user is sent to after signing in, chosen on the role itself.
     *
     * Stored as a catalogue key (not a free URL) so an admin cannot point a
     * role at an arbitrary destination.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('login_redirect')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('login_redirect');
        });
    }
};
