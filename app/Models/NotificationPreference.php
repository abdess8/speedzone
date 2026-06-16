<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'invoice_generated',
        'ticket_created',
        'ticket_message',
        'ticket_closed',
        'return_requested',
        'system_notifications',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'invoice_generated' => 'boolean',
        'ticket_created' => 'boolean',
        'ticket_message' => 'boolean',
        'ticket_closed' => 'boolean',
        'return_requested' => 'boolean',
        'system_notifications' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTypeEnabled(NotificationType $type): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return (bool) $this->{$type->value};
    }
}
