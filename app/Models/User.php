<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'city',
        'address',
        'pickup_address_1',
        'pickup_address_2',
        'phone_number',
        'cin',
        'ice_number',
        'photo',
        'attached_files',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'attached_files' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'photo_url',
        'attached_files_urls',
        'full_name',
    ];

    /**
     * The role that the user belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Orders created by this user acting as the seller.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function pickupRequestsCreated(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'created_by');
    }

    public function pickupRequestsAssigned(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'assigned_to');
    }

    /**
     * Delivery sectors this driver is assigned to serve.
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'driver_sector', 'user_id', 'sector_id')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    /**
     * Whether the user holds the Driver role.
     */
    public function isDriver(): bool
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->roles->contains(fn (Role $role) => $role->name === Role::DRIVER);
    }

    public function isSeller(): bool
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->roles->contains(fn (Role $role) => $role->name === Role::SELLER);
    }

    /**
     * Roles that are granted unrestricted access across the application.
     *
     * @var array<int, string>
     */
    public const SUPER_ADMIN_ROLES = ['Admin', 'SuperAdmin'];

    /**
     * Whether the user belongs to an all-access (super admin) role.
     */
    public function isSuperAdmin(): bool
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->roles->contains(fn (Role $role) => in_array($role->name, self::SUPER_ADMIN_ROLES, true));
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles.permissions');
        } elseif (! $this->roles->every(fn (Role $role) => $role->relationLoaded('permissions'))) {
            $this->load('roles.permissions');
        }

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->contains(fn (Permission $item) => $item->name === $permission);
    }

    /**
     * Scope-aware permission check for order access controls.
     */
    public function hasOrderScopePermission(string $action, ?Order $order = null): bool
    {
        if ($this->hasPermission("orders.{$action}.all")) {
            return true;
        }

        if ($order && $order->seller_id === $this->id) {
            return $this->hasPermission("orders.{$action}.own");
        }

        return false;
    }

    /**
     * Scope-aware permission check for pickup request access controls.
     */
    public function hasPickupRequestScopePermission(string $action, ?PickupRequest $pickup = null): bool
    {
        if ($this->hasPermission('pickup_requests.read.all')) {
            return true;
        }

        if ($pickup && $pickup->assigned_to === $this->id && $this->hasPermission('pickup_requests.read.assigned')) {
            return $action === 'read' || ($action === 'pickup' && $this->hasPermission('pickup_requests.pickup'));
        }

        if ($pickup && $pickup->created_by === $this->id && $this->hasPermission('pickup_requests.read.own')) {
            return $action === 'read';
        }

        if (! $pickup && $action === 'read' && $this->hasPermission('pickup_requests.read.own')) {
            return true;
        }

        return false;
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        $fullName = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        return $fullName !== '' ? $fullName : (string) $this->name;
    }

    /**
     * Get the public URL of the uploaded profile photo.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /**
     * Get the list of attached file URLs with their original names.
     *
     * @return array<int, array<string, string>>
     */
    public function getAttachedFilesUrlsAttribute(): array
    {
        return collect($this->attached_files ?? [])
            ->map(fn ($file) => [
                'name' => $file['name'] ?? basename($file['path'] ?? ''),
                'path' => $file['path'] ?? '',
                'url' => isset($file['path']) ? Storage::disk('public')->url($file['path']) : '',
            ])
            ->values()
            ->all();
    }
}
