<?php

namespace App\Models;

use App\Enums\PartnerOrderField;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'speedzone_field',
        'partner_field',
    ];

    protected $casts = [
        'speedzone_field' => PartnerOrderField::class,
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
