<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field of a product sheet, before and after an edit.
 */
class ProductHistory extends Model
{
    use HasFactory;

    protected $table = 'product_histories';

    protected $fillable = [
        'product_id',
        'changed_by',
        'field_name',
        'old_value',
        'new_value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
