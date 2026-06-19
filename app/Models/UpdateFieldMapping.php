<?php

namespace App\Models;

use App\Enums\PartnerUpdateField;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpdateFieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'speedzone_field',
        'partner_field',
    ];

    protected $casts = [
        'speedzone_field' => PartnerUpdateField::class,
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
