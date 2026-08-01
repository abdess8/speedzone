<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLog extends Model
{
    use HasFactory;

    public const ACTION_CREATED = 'created';

    public const ACTION_STATUS_CHANGED = 'status_changed';

    public const ACTION_PAID = 'paid';

    public const ACTION_CANCELLED = 'cancelled';

    public const ACTION_DELETED = 'deleted';

    protected $fillable = [
        'invoice_id',
        'action',
        'user_id',
        'old_value',
        'new_value',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
