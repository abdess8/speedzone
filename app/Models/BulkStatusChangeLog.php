<?php

namespace App\Models;

use App\Enums\BulkStatusEntityType;
use App\Enums\BulkStatusFailureReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempted item of a bulk status edit.
 *
 * @property string $batch_id
 */
class BulkStatusChangeLog extends Model
{
    /** Set by the wizard and by the mobile quick action alike. */
    public const SOURCE_BULK_EDIT = 'BULK_EDIT';

    /** Selection made by scanning the parcel's QR code. */
    public const SOURCE_QR_SCAN = 'BULK_EDIT_SCAN';

    protected $fillable = [
        'batch_id',
        'user_id',
        'entity_type',
        'entity_id',
        'reference',
        'from_status',
        'to_status',
        'successful',
        'failure_reason',
        'failure_message',
        'source',
    ];

    protected $casts = [
        'entity_type' => BulkStatusEntityType::class,
        'failure_reason' => BulkStatusFailureReason::class,
        'successful' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
