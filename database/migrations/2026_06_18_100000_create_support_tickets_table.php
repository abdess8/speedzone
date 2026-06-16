<?php

use App\Enums\SupportTicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller support tickets. Each ticket is opened by a seller against an
 * operational object (order, invoice, pickup request) and handled by support
 * staff through a tracked chat conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Polymorphic-style link kept as a constrained enum + nullable id so
            // general tickets (no object) are also supported.
            $table->string('object_type')->nullable();
            $table->unsignedBigInteger('object_id')->nullable();

            $table->string('category');
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default(SupportTicketStatus::OPEN->value);

            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('created_by');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('category');
            $table->index('created_at');
            $table->index(['object_type', 'object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
