<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'seller_id',
        'customer_first_name',
        'customer_last_name',
        'customer_phone',
        'customer_address',
        'city_id',
        'sector_id',
        'payment_method',
        'order_amount',
        'delivery_price',
        'total_amount',
        'notes',
        'is_fragile',
        'can_be_opened',
        'status',
    ];

    protected $casts = [
        'payment_method' => PaymentMethod::class,
        'status' => OrderStatus::class,
        'order_amount' => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_fragile' => 'boolean',
        'can_be_opened' => 'boolean',
    ];

    protected $appends = [
        'customer_full_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $order): void {
            if (empty($order->status)) {
                $order->status = OrderStatus::CREATED->value;
            }

            // Total is always derived from order amount + delivery price.
            $order->total_amount = round((float) $order->order_amount + (float) $order->delivery_price, 2);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestStatusHistory(): HasOne
    {
        return $this->hasOne(OrderStatusHistory::class)->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Record a status change in the order history.
     */
    public function recordStatus(OrderStatus|string $status, ?User $actor = null, ?string $comment = null): OrderStatusHistory
    {
        return $this->statusHistories()->create([
            'status' => $status instanceof OrderStatus ? $status->value : $status,
            'user_id' => $actor?->id,
            'comment' => $comment,
        ]);
    }

    /**
     * Public tracking URL encoded inside the QR code / shipping label.
     */
    public function trackingUrl(): string
    {
        $base = rtrim((string) config('orders.tracking_base_url', config('app.url')), '/');

        return $base.'/orders/'.$this->tracking_number;
    }

    public function getCustomerFullNameAttribute(): string
    {
        return trim("{$this->customer_first_name} {$this->customer_last_name}");
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Restrict the query to orders owned by the given seller.
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('seller_id', $userId);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
