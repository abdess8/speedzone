<?php

namespace App\Models;

use App\Casts\PaymentMethodCast;
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
        'pickup_request_id',
        'customer_first_name',
        'customer_last_name',
        'customer_phone',
        'customer_address',
        'city_id',
        'sector_id',
        'payment_method',
        'order_value',
        'order_amount',
        'delivery_price',
        'total_amount',
        'notes',
        'is_fragile',
        'can_be_opened',
        'status',
    ];

    protected $casts = [
        'payment_method' => PaymentMethodCast::class,
        'status' => OrderStatus::class,
        'order_value' => 'decimal:2',
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

            $payment = $order->payment_method instanceof PaymentMethod
                ? $order->payment_method
                : PaymentMethod::resolve((string) $order->payment_method);

            if ($payment === PaymentMethod::CASH) {
                $order->order_value = $order->order_amount;
            }

            $orderAmount = (float) ($order->order_amount ?? 0);
            $order->total_amount = round($orderAmount + (float) $order->delivery_price, 2);
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

    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
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

    public function scopeEligibleForPickup(Builder $query, int $sellerId): Builder
    {
        return $query
            ->where('seller_id', $sellerId)
            ->where('status', OrderStatus::CREATED)
            ->whereNull('pickup_request_id');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
