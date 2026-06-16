<?php

namespace App\Models;

use App\Enums\SupportObjectType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'created_by',
        'assigned_to',
        'object_type',
        'object_id',
        'category',
        'subject',
        'message',
        'status',
        'last_reply_at',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'object_type' => SupportObjectType::class,
        'category' => SupportTicketCategory::class,
        'status' => SupportTicketStatus::class,
        'last_reply_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function statusEnum(): SupportTicketStatus
    {
        return $this->status instanceof SupportTicketStatus
            ? $this->status
            : SupportTicketStatus::from($this->status);
    }

    public function isClosed(): bool
    {
        return $this->statusEnum()->isClosed();
    }

    /**
     * Resolve the related operational object (order / invoice / pickup request).
     */
    public function resolveObject(): ?Model
    {
        if (! $this->object_type || ! $this->object_id) {
            return null;
        }

        $type = $this->object_type instanceof SupportObjectType
            ? $this->object_type
            : SupportObjectType::tryFrom((string) $this->object_type);

        if (! $type) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = $type->modelClass();

        return $model::query()->find($this->object_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
