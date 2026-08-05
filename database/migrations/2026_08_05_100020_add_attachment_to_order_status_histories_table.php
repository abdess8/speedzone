<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proof of a failed delivery — a photo of the closed shop, a screenshot of
     * the unanswered call — belongs to the attempt that produced it, so it is
     * stored on the history row rather than on the order, where a second
     * attempt would overwrite the first.
     */
    public function up(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->string('attachment_path')->nullable()->after('comment');
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }
};
