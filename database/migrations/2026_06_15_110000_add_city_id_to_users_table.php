<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('last_name')
                ->constrained('cities')
                ->nullOnDelete();
        });

        // Best-effort backfill from legacy free-text city column.
        if (Schema::hasColumn('users', 'city')) {
            $cities = DB::table('cities')->pluck('id', 'name');

            DB::table('users')
                ->whereNull('city_id')
                ->whereNotNull('city')
                ->orderBy('id')
                ->get()
                ->each(function (object $user) use ($cities): void {
                    $cityId = $cities[$user->city] ?? null;

                    if ($cityId) {
                        DB::table('users')->where('id', $user->id)->update(['city_id' => $cityId]);
                    }
                });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('city');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('city')->nullable()->after('last_name');
        });

        DB::table('users')
            ->join('cities', 'cities.id', '=', 'users.city_id')
            ->select('users.id', 'cities.name')
            ->orderBy('users.id')
            ->get()
            ->each(function (object $row): void {
                DB::table('users')->where('id', $row->id)->update(['city' => $row->name]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
