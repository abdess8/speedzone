<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasFactory;

    /**
     * These logs are append-only: we record created_at but never touch them
     * again, so there is no updated_at column.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'partner_id',
        'action',
        'method',
        'endpoint',
        'request_payload',
        'response_payload',
        'status_code',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
