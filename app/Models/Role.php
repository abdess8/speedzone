<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

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
