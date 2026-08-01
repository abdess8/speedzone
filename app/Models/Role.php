<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory;

    public const ADMIN = 'Admin';

    public const DISPATCHER = 'Dispatcher';

    public const DRIVER = 'Driver';

    public const SELLER = 'Seller';

    public const SUPER_ADMIN = self::ADMIN;

    public const VENDEUR = self::SELLER;

    public const LIVREUR = self::DRIVER;

    public const PARTENAIRE = 'Partner';

    /**
     * The default roles seeded into the application.
     *
     * @var array<int, string>
     */
    public const DEFAULTS = [
        self::ADMIN,
        self::DISPATCHER,
        self::DRIVER,
        self::SELLER,
    ];

    /**
     * Prefix of the generated `name` of a vendor-defined role.
     *
     * Custom roles keep a namespaced internal name and expose their human label
     * through `label`, so `roles.name` can retain its global unique index and
     * the many existing where('name', Role::SELLER) lookups can never be
     * matched by a vendor role that happens to be called "Seller".
     */
    public const VENDOR_PREFIX = 'vendor.';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'label',
        'owner_id',
    ];

    /**
     * Human readable name, whatever the role's origin.
     */
    public function displayName(): string
    {
        return $this->label ?: $this->name;
    }

    public function isCustom(): bool
    {
        return $this->owner_id !== null;
    }

    /**
     * Platform roles (Admin, Dispatcher, Driver, Seller).
     *
     * @param  Builder<Role>  $query
     */
    public function scopeSystem($query): void
    {
        $query->whereNull('owner_id');
    }

    /**
     * @param  Builder<Role>  $query
     */
    public function scopeOwnedBy($query, int $ownerId): void
    {
        $query->where('owner_id', $ownerId);
    }

    /**
     * Internal name for a vendor role, unique across the platform.
     */
    public static function vendorName(int $ownerId, string $label): string
    {
        return self::VENDOR_PREFIX.$ownerId.'.'.Str::slug($label);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Backward-compatible single-role relation for legacy screens using `users.role_id`.
     */
    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }
}
