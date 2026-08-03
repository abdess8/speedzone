<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_guide_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The guide identifier as declared in GuideCatalog, not a foreign
            // key: guides are code, not data, so a removed guide should leave a
            // harmless orphan row rather than break the migration history.
            $table->string('guide_key', 64);

            // Where the reader stopped, so an interrupted tour can be offered
            // again from the step that lost him rather than from the welcome.
            $table->unsignedSmallInteger('last_step_index')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Replays are the normal case (the Help Center invites them), so
            // "has completed at least once" and "how many times" are different
            // questions and both are worth answering.
            $table->unsignedSmallInteger('completed_count')->default(0);

            $table->timestamps();

            // One row per reader and guide: every write is an upsert on this key.
            $table->unique(['user_id', 'guide_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_guide_progress');
    }
};
