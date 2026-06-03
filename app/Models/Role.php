<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const SUPER_ADMIN = 'SuperAdmin';
    public const ADMIN = 'Admin';
    public const VENDEUR = 'Vendeur';
    public const LIVREUR = 'Livreur';
    public const PARTENAIRE = 'Partenaire';

    /**
     * The default roles seeded into the application.
     *
     * @var array<int, string>
     */
    public const DEFAULTS = [
        self::SUPER_ADMIN,
        self::ADMIN,
        self::VENDEUR,
        self::LIVREUR,
        self::PARTENAIRE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * The users that belong to the role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
