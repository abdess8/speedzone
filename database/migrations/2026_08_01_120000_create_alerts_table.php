<?php

use App\Enums\AlertFormat;
use App\Enums\AlertType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('type', AlertType::values())->default(AlertType::INFO->value);
            $table->enum('display_format', AlertFormat::values())->default(AlertFormat::BANNER->value);

            // Banners only: a modal is always closable, otherwise the user
            // cannot get past it.
            $table->boolean('is_dismissible')->default(true);

            // Audience. An entry of `all` widens the dimension to everyone; an
            // empty array narrows it to nobody, which is how an alert aimed at
            // a hand-picked list of people is expressed.
            $table->json('target_roles');
            $table->json('target_cities');
            $table->json('target_user_ids');

            $table->dateTime('end_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Every authenticated request asks for the alerts on display, and
            // that lookup is always "active and not expired yet".
            $table->index(['is_active', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
