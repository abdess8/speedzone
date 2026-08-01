<?php

namespace App\Models;

use App\Enums\AlertFormat;
use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An announcement an administrator puts in front of a group of users, either as
 * a banner across the top of every page or as a modal on their next sign-in.
 */
class Alert extends Model
{
    use HasFactory;

    /**
     * Widens a targeting dimension to everyone.
     *
     * Stored inside the array rather than as an empty array on purpose: an
     * empty list has to keep meaning "nobody", so that an alert addressed to a
     * hand-picked set of people does not leak to the whole company.
     */
    public const EVERYONE = 'all';

    protected $fillable = [
        'title',
        'message',
        'type',
        'display_format',
        'is_dismissible',
        'target_roles',
        'target_cities',
        'target_user_ids',
        'end_date',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'type' => AlertType::class,
        'display_format' => AlertFormat::class,
        'is_dismissible' => 'boolean',
        'is_active' => 'boolean',
        'target_roles' => 'array',
        'target_cities' => 'array',
        'target_user_ids' => 'array',
        'end_date' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alerts that should be on screen right now, newest first.
     */
    public function scopeOnAir(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('end_date', '>', Carbon::now())
            ->orderByDesc('created_at');
    }

    public function hasExpired(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast();
    }

    /**
     * `active`, `expired` or `disabled`, as shown in the management table.
     *
     * Expiry wins over the manual switch: an administrator reading "expired"
     * needs to know the alert is off the air whatever the toggle says.
     */
    public function status(): string
    {
        return match (true) {
            $this->hasExpired() => 'expired',
            ! $this->is_active => 'disabled',
            default => 'active',
        };
    }

    public function isBanner(): bool
    {
        return $this->display_format === AlertFormat::BANNER;
    }

    /**
     * A modal has to be closable or it locks the interface, so only banners
     * honour the persistent setting.
     */
    public function canBeDismissed(): bool
    {
        return ! $this->isBanner() || $this->is_dismissible;
    }

    /**
     * Whether this alert is addressed to the given user.
     *
     * Roles and cities narrow each other — "Sellers" plus "Tangier" means
     * sellers in Tangier, not every seller and every resident. The explicit
     * user list is added on top of that audience rather than narrowing it, so
     * an administrator can always reach a few extra people by name.
     *
     * @param  array<int, string>  $roleNames  role names held by the user
     * @param  array<int, int>  $cityIds  every city the user is attached to
     */
    public function targets(int $userId, array $roleNames, array $cityIds): bool
    {
        if ($this->listsUser($userId)) {
            return true;
        }

        return $this->matchesDimension($this->target_roles, $roleNames)
            && $this->matchesDimension($this->target_cities, $cityIds);
    }

    public function listsUser(int $userId): bool
    {
        return in_array($userId, array_map('intval', $this->target_user_ids ?? []), true);
    }

    /**
     * True when the dimension is open to everyone, or when the user's values
     * overlap the selected ones. An empty selection matches nobody.
     *
     * @param  array<int, mixed>|null  $selected
     * @param  array<int, mixed>  $held
     */
    private function matchesDimension(?array $selected, array $held): bool
    {
        $selected ??= [];

        if (in_array(self::EVERYONE, $selected, true)) {
            return true;
        }

        return array_intersect(
            array_map('strval', $selected),
            array_map('strval', $held)
        ) !== [];
    }
}
