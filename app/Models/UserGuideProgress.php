<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reader's history with one interactive guide.
 *
 * The client also mirrors the "already completed" set into localStorage so the
 * common check — "may I auto-start this tour?" — costs no request. This table
 * is what makes that answer survive a new device, and what the Help Center
 * reads to show progress.
 */
class UserGuideProgress extends Model
{
    protected $table = 'user_guide_progress';

    protected $fillable = [
        'user_id',
        'guide_key',
        'last_step_index',
        'started_at',
        'completed_at',
        'completed_count',
    ];

    protected $casts = [
        'last_step_index' => 'integer',
        'completed_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
