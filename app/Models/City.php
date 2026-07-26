<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class City extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const OPTIONS_CACHE_KEY = 'cities.active.options';

    protected $fillable = [
        'name',
        'code',
        'region',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function activeSectors(): HasMany
    {
        return $this->hasMany(Sector::class)->where('is_active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Partners that delegate deliveries to speedZone in this city.
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'partner_city')->withTimestamps();
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

    /*
    |--------------------------------------------------------------------------
    | Cached dropdown options
    |--------------------------------------------------------------------------
    */

    /**
     * Active cities for dropdowns. The table is small and almost static, yet it
     * was queried on every orders/users/transfers page render, so it is cached
     * until a city is written.
     *
     * @return array<int, array{id: int, name: string, code: ?string, region: ?string}>
     */
    public static function options(): array
    {
        return Cache::remember(
            self::OPTIONS_CACHE_KEY,
            (int) config('performance.reference_cache_ttl', 3600),
            fn () => self::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region'])
                ->map(fn (self $city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                    'code' => $city->code,
                    'region' => $city->region,
                ])
                ->all()
        );
    }

    protected static function booted(): void
    {
        $flush = static fn () => Cache::forget(self::OPTIONS_CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }
}
