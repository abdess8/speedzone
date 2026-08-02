<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cities no longer carry their own delivery price (it now lives on the
     * sector). We also add an optional code and enable soft deletes so that a
     * city referenced by orders/sectors can be retired without data loss.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            if (! Schema::hasColumn('cities', 'code')) {
                $table->string('code')->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('cities', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('cities', 'delivery_price')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropColumn('delivery_price');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            if (! Schema::hasColumn('cities', 'delivery_price')) {
                $table->decimal('delivery_price', 12, 2)->default(0)->after('region');
            }

            if (Schema::hasColumn('cities', 'code')) {
                $table->dropUnique('cities_code_unique');
                $table->dropColumn('code');
            }

            if (Schema::hasColumn('cities', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
