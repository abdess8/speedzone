<?php

namespace App\Models;

use App\Enums\PartnerAuthType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_url',
        'ice_number',
        'is_active',
        'reception_city_id',
        'api_base_url',
        'client_id',
        'client_secret',
        'auth_type',
        'endpoint_statuses',
        'endpoint_deliveries',
        'delivery_lookup_param',
        'endpoint_update',
        'endpoint_login',
        'api_key_header',
        'login_username_field',
        'login_password_field',
        'login_token_field',
        'access_token',
        'token_expires_at',
        'sync_frequency_minutes',
        'last_synced_at',
        'sync_status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sync_status' => 'boolean',
        'auth_type' => PartnerAuthType::class,
        'client_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'sync_frequency_minutes' => 'integer',
        'last_synced_at' => 'datetime',
        'token_expires_at' => 'datetime',
    ];

    /**
     * Keep partner credentials out of serialized payloads (Inertia/JSON).
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'client_secret',
        'access_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Hub city where the partner drops off packages for OWL Delivery to deliver.
     */
    public function receptionCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'reception_city_id');
    }

    /**
     * Cities this partner delegates delivery for.
     */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'partner_city')->withTimestamps();
    }

    /**
     * Sectors this partner delegates within their delegated cities.
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'partner_sector')->withTimestamps();
    }

    /**
     * Users allowed to manage this partner's deliveries (RBAC).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'partner_user')->withTimestamps();
    }

    public function statusMappings(): HasMany
    {
        return $this->hasMany(StatusMapping::class);
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(FieldMapping::class);
    }

    public function updateFieldMappings(): HasMany
    {
        return $this->hasMany(UpdateFieldMapping::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Public URL for the partner logo (stored path or external URL).
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
                return $value;
            }

            return User::publicStorageUrl($value);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Whether enough time has elapsed since the last sync to run another one.
     */
    public function isDueForSync(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->last_synced_at === null) {
            return true;
        }

        return $this->last_synced_at
            ->copy()
            ->addMinutes($this->sync_frequency_minutes)
            ->isPast();
    }

    /**
     * Whether the given user may manage this partner's deliveries.
     *
     * Super admins always pass; otherwise the user must be linked through the
     * partner_user pivot.
     */
    public function isManageableBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->users()->whereKey($user->id)->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
