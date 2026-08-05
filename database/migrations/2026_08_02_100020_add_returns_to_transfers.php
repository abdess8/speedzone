<?php

use App\Enums\TransferContentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('content_type')
                ->default(TransferContentType::ORDERS->value)
                ->after('status');

            $table->unsignedInteger('number_of_returns')->default(0)->after('number_of_packages');

            $table->index('content_type');
        });

        Schema::create('transfer_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Unlike transfer_orders, the pair is unique rather than the return
            // alone: a cancelled manifest drops its rows and puts the parcel
            // back in the pool, so the same return can ride a later truck.
            $table->unique(['transfer_id', 'return_id']);
            $table->index('return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_returns');

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex(['content_type']);
            $table->dropColumn(['content_type', 'number_of_returns']);
        });
    }
};
