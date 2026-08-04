<?php

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\ReturnStatus;
use App\Enums\SellerPaymentMethod;
use App\Enums\UserStatus;
use App\Notifications\VerifyOwlDeliveryAccountEmail;
use App\Support\StoreContext;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'parent_user_id',
        'status',
        'name',
        'first_name',
        'last_name',
        'email',
        'locale',
        'password',
        'city_id',
        'address',
        'pickup_address_1',
        'pickup_address_2',
        'phone_number',
        'cin',
        'ice_number',
        'photo',
        'profile_photo_path',
        'attached_files',
        'billing_frequency',
        'next_billing_date',
        'billing_enabled',
        'payment_method',
        'bank_name',
        'rib',
        'rib_attachment',
        'cin_front_attachment',
        'cin_back_attachment',
        'default_store_name',
        'default_store_logo',
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
        'status' => UserStatus::class,
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'attached_files' => 'array',
        'billing_frequency' => BillingFrequency::class,
        'payment_method' => SellerPaymentMethod::class,
        'next_billing_date' => 'date',
        'billing_enabled' => 'boolean',
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
        'rib_attachment_url',
        'cin_front_attachment_url',
        'cin_back_attachment_url',
    ];

    /**
     * The role that the user belongs to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-store account
    |--------------------------------------------------------------------------
    |
    | A vendor account is a tree one level deep: the seller who signed up, plus
    | the team members he creates. Ownership of business rows always stays on
    | the seller (accountOwnerId), while day-to-day visibility is narrowed to
    | the store the actor is currently standing on.
    */

    /**
     * The vendor account this user belongs to (null for a vendor admin).
     */
    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_user_id');
    }

    /**
     * Team members created under this vendor account.
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(self::class, 'parent_user_id');
    }

    /**
     * Stores this user may switch into (owner and team members alike).
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class)->withTimestamps();
    }

    /**
     * Stores this user owns as a vendor admin.
     */
    public function ownedStores(): HasMany
    {
        return $this->hasMany(Store::class, 'owner_id');
    }

    /**
     * History with the interactive guides of the Help Center.
     */
    public function guideProgress(): HasMany
    {
        return $this->hasMany(UserGuideProgress::class);
    }

    /**
     * Id of the account that owns the data — this user, or the vendor he works
     * for. Every `.own` permission scope resolves through this, which is what
     * lets a team member read his employer's orders without owning them.
     */
    public function accountOwnerId(): int
    {
        return $this->parent_user_id ?? $this->id;
    }

    public function isTeamMember(): bool
    {
        return $this->parent_user_id !== null;
    }

    /**
     * Whether this user's visibility is bound to a store.
     *
     * Staff accounts are excluded: a dispatcher must keep seeing every seller's
     * orders, and a super admin must never be boxed into one shop.
     */
    public function belongsToStoreAccount(): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return $this->isSeller() || $this->isTeamMember();
    }

    /**
     * Per-instance memo of the accessible store ids.
     *
     * @var array<int, int>|null
     */
    protected ?array $accessibleStoreIdMemo = null;

    /**
     * Ids of the active stores this user may read, ordered with the default
     * one first so the UI has a stable, meaningful ordering.
     *
     * @return array<int, int>
     */
    public function accessibleStoreIds(): array
    {
        if ($this->accessibleStoreIdMemo !== null) {
            return $this->accessibleStoreIdMemo;
        }

        return $this->accessibleStoreIdMemo = $this->stores()
            ->where('stores.is_active', true)
            ->orderByDesc('stores.is_default')
            ->orderBy('stores.name')
            ->pluck('stores.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function canAccessStore(int $storeId): bool
    {
        return in_array($storeId, $this->accessibleStoreIds(), true);
    }

    /**
     * Store pre-selected at login when the user has not chosen one yet.
     */
    public function defaultStoreId(): ?int
    {
        return $this->accessibleStoreIds()[0] ?? null;
    }

    public function forgetStoreMemo(): void
    {
        $this->accessibleStoreIdMemo = null;
    }

    /**
     * Whether a row belongs to the store currently being viewed.
     *
     * Always true when no store boundary is in force (staff accounts, queued
     * jobs, console commands), so non-store actors are unaffected.
     */
    private function sharesActiveStore(?int $storeId): bool
    {
        $context = app(StoreContext::class);

        if (! $context->isEnforced()) {
            return true;
        }

        return $storeId !== null && $storeId === $context->id();
    }

    /**
     * B2B partners whose deliveries this user is allowed to manage (RBAC).
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'partner_user')->withTimestamps();
    }

    /**
     * Orders created by this user acting as the seller.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    /**
     * Invoices issued to this user acting as the seller.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'seller_id');
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Private broadcast channel for real-time notifications.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'user.'.$this->id;
    }

    /**
     * Orders delivered (or assigned to be delivered) by this driver.
     */
    public function deliveredOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    /**
     * Financial transactions earned by this user acting as a driver.
     */
    public function driverTransactions(): HasMany
    {
        return $this->hasMany(DriverTransaction::class, 'driver_id');
    }

    /**
     * Settlement invoices issued to this user acting as a driver.
     */
    public function driverInvoices(): HasMany
    {
        return $this->hasMany(DriverInvoice::class, 'driver_id');
    }

    /**
     * Support tickets opened by this user (as a seller).
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'created_by');
    }

    /**
     * Support tickets currently assigned to this user (as support staff).
     */
    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    public function pickupRequestsCreated(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'created_by');
    }

    public function pickupRequestsAssigned(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'assigned_to');
    }

    public function transfersCreated(): HasMany
    {
        return $this->hasMany(Transfer::class, 'created_by');
    }

    public function transfersAssigned(): HasMany
    {
        return $this->hasMany(Transfer::class, 'assigned_to');
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
     * Per-instance memo of role names, keyed for O(1) lookups.
     *
     * @var array<string, true>|null
     */
    protected ?array $roleNameMemo = null;

    /**
     * Per-instance memo of every granted permission name (roles + direct),
     * keyed for O(1) lookups.
     *
     * @var array<string, true>|null
     */
    protected ?array $permissionNameMemo = null;

    /**
     * All role names held by this user, keyed for O(1) lookups.
     *
     * @return array<string, true>
     */
    protected function roleNameMap(): array
    {
        if ($this->roleNameMemo !== null) {
            return $this->roleNameMemo;
        }

        $this->loadMissing('roles');

        return $this->roleNameMemo = array_fill_keys(
            $this->roles->pluck('name')->all(),
            true
        );
    }

    /**
     * All permission names granted through roles or assigned directly.
     *
     * Resolved once per instance: without the memo a single request re-walks
     * roles → permissions for every hasPermission() call (dozens per page).
     *
     * @return array<string, true>
     */
    protected function permissionNameMap(): array
    {
        if ($this->permissionNameMemo !== null) {
            return $this->permissionNameMemo;
        }

        $this->loadMissing('roles.permissions', 'permissions');

        $names = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->merge($this->permissions->pluck('name'))
            ->unique()
            ->all();

        return $this->permissionNameMemo = array_fill_keys($names, true);
    }

    /**
     * Every permission name granted to this user.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return array_keys($this->permissionNameMap());
    }

    /**
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        return array_keys($this->roleNameMap());
    }

    /**
     * Drop the memoized role/permission lookups (call after granting or
     * revoking access on an already-loaded instance).
     */
    public function forgetAccessMemo(): void
    {
        $this->roleNameMemo = null;
        $this->permissionNameMemo = null;
    }

    /**
     * Per-instance memo of the cities this user is attached to.
     *
     * @var array<int, int>|null
     */
    protected ?array $cityIdMemo = null;

    /**
     * Every city this user can be considered part of.
     *
     * There is no single answer in this schema. A seller carries a city on the
     * profile but may run shops in several others; a driver usually has no
     * profile city at all and is instead reachable through the sectors assigned
     * to them. Targeting an announcement at "Tangier" has to find all of them,
     * so this unions the three paths.
     *
     * @return array<int, int>
     */
    public function cityIds(): array
    {
        if ($this->cityIdMemo !== null) {
            return $this->cityIdMemo;
        }

        $ids = collect([$this->city_id])
            ->merge($this->stores()->pluck('stores.city_id'))
            ->merge($this->sectors()->pluck('sectors.city_id'));

        if ($this->isAccountOwner()) {
            $ids = $ids->merge($this->ownedStores()->pluck('city_id'));
        }

        return $this->cityIdMemo = $ids
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Users who can be considered part of a city.
     *
     * The query-side counterpart of cityIds(), narrowed to the two paths that
     * matter when looking for somebody to send into the field: the sectors he
     * drives and, failing that, the city on his profile. Shop cities are left out
     * on purpose — owning a shop in Tangier does not make a vendor available to
     * work there.
     */
    public function scopeCoveringCity(Builder $query, int $cityId): Builder
    {
        return $query->where(function (Builder $scoped) use ($cityId): void {
            $scoped->whereHas('sectors', fn (Builder $sectors) => $sectors->where('sectors.city_id', $cityId))
                ->orWhere('users.city_id', $cityId);
        });
    }

    /**
     * Whether the user holds the Driver role.
     */
    public function isDriver(): bool
    {
        return isset($this->roleNameMap()[Role::DRIVER]);
    }

    /**
     * Whether the user acts on the vendor side of the platform.
     *
     * Team members hold a custom role instead of the global Seller role — they
     * must never inherit its full permission set — but they still belong to a
     * vendor account, so seller-side affordances have to recognise them.
     */
    public function isSeller(): bool
    {
        return isset($this->roleNameMap()[Role::SELLER]) || $this->isTeamMember();
    }

    /**
     * The vendor admin: owns the stores and administers the team.
     */
    public function isAccountOwner(): bool
    {
        return ! $this->isTeamMember() && isset($this->roleNameMap()[Role::SELLER]);
    }

    public function isSuspended(): bool
    {
        return ($this->status ?? UserStatus::Active) === UserStatus::Suspended;
    }

    /**
     * Custom roles this vendor defined for his team.
     */
    public function customRoles(): HasMany
    {
        return $this->hasMany(Role::class, 'owner_id');
    }

    /**
     * Whether the user may manage the given partner's deliveries.
     *
     * Super admins always pass; otherwise the user must be linked through the
     * partner_user pivot.
     */
    public function managesPartner(Partner $partner): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->partners()->whereKey($partner->id)->exists();
    }

    /**
     * Whether the user may view and manage all partners (bypasses partner_user scoping).
     *
     * French permission label: "Voir les partenaires" / gérer tous les partenaires.
     */
    public function canManageAllPartners(): bool
    {
        return $this->hasPermission('partners.read');
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
        $roles = $this->roleNameMap();

        foreach (self::SUPER_ADMIN_ROLES as $role) {
            if (isset($roles[$role])) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasRolePermission($permission);
    }

    /**
     * Check a permission as assigned on roles, without super-admin bypass.
     */
    public function hasRolePermission(string $permission): bool
    {
        return isset($this->permissionNameMap()[$permission]);
    }

    public function isAccountActive(): bool
    {
        return ($this->status ?? UserStatus::Active) === UserStatus::Active;
    }

    public function isPendingApproval(): bool
    {
        return ($this->status ?? UserStatus::Active) === UserStatus::PendingApproval;
    }

    public function isRegistrationRejected(): bool
    {
        return ($this->status ?? UserStatus::Active) === UserStatus::Rejected;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyOwlDeliveryAccountEmail);
    }

    /**
     * Whether the user may request a return as a seller (seller role only).
     */
    public function canCreateReturnRequest(): bool
    {
        return $this->isSeller() && $this->hasRolePermission('returns.create_request');
    }

    /**
     * Whether the user may initiate a failed-delivery return as a driver.
     */
    public function canCreateDriverReturn(): bool
    {
        return $this->isDriver() && $this->hasRolePermission('returns.create');
    }

    /**
     * Whether the user may advance a return through at least one of its steps.
     *
     * The workflow grants can be held one step at a time — a hub manager signs
     * parcels in, a driver closes them at the seller's door — so the coarse
     * "can this user touch return statuses?" question has to consider both the
     * blanket permission and the per-step ones.
     */
    public function canUpdateReturnStatus(): bool
    {
        if ($this->hasPermission('returns.update_status') || $this->hasPermission('returns.manage')) {
            return true;
        }

        foreach (ReturnStatus::pipeline() as $status) {
            foreach ($status->allowedBy() as $permission) {
                if (str_starts_with($permission, 'returns.transition.') && $this->hasPermission($permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the returns module should appear in navigation.
     */
    public function canAccessReturnsModule(): bool
    {
        if ($this->isSeller()) {
            return true;
        }

        return $this->hasRolePermission('returns.read.all')
            || $this->hasRolePermission('returns.read.own')
            || $this->hasRolePermission('returns.create_request')
            || $this->hasRolePermission('returns.create')
            || $this->hasRolePermission('returns.manage')
            || $this->canUpdateReturnStatus();
    }

    /**
     * Scope-aware permission check for order access controls.
     *
     * Resolution order is always "all" (pure RBAC) → "own" (seller_id) →
     * "assigned" (driver_id), the last two forming the ABAC layer. Passing a
     * null order answers the listing question ("may this user reach the index
     * at all?"), where holding any row-level scope is sufficient.
     */
    public function hasOrderScopePermission(string $action, ?Order $order = null): bool
    {
        if ($this->hasPermission("orders.{$action}.all")) {
            return true;
        }

        if (! $order) {
            return $this->hasPermission("orders.{$action}.own")
                || $this->hasPermission("orders.{$action}.assigned");
        }

        if ((int) $order->seller_id === $this->accountOwnerId()
            && $this->sharesActiveStore($order->store_id)
            && $this->hasPermission("orders.{$action}.own")) {
            return true;
        }

        return (int) $order->driver_id === (int) $this->id
            && $this->hasPermission("orders.{$action}.assigned");
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

        if ($pickup
            && (int) $pickup->created_by === $this->accountOwnerId()
            && $this->sharesActiveStore($pickup->store_id)
            && $this->hasPermission('pickup_requests.read.own')) {
            return $action === 'read';
        }

        if (! $pickup && $action === 'read' && $this->hasPermission('pickup_requests.read.own')) {
            return true;
        }

        return false;
    }

    /**
     * Scope-aware permission check for transfer access controls.
     */
    public function hasTransferScopePermission(string $action, ?Transfer $transfer = null): bool
    {
        if ($this->hasPermission('transfers.read')) {
            return true;
        }

        if ($transfer && $transfer->assigned_to === $this->id && $this->hasPermission('transfers.read.assigned')) {
            return in_array($action, ['read', 'receive', 'scan'], true);
        }

        return false;
    }

    /**
     * Scope-aware permission check for return access controls.
     */
    public function hasReturnScopePermission(string $action, ?OrderReturn $return = null): bool
    {
        if ($this->hasPermission('returns.read.all') || $this->hasPermission('returns.manage')) {
            return true;
        }

        if ($return
            && (int) $return->order?->seller_id === $this->accountOwnerId()
            && $this->sharesActiveStore($return->store_id)
            && $this->hasPermission('returns.read.own')) {
            return $action === 'read' || ($action === 'edit_customer_data' && $this->canCreateReturnRequest());
        }

        if ($return && $return->created_by === $this->id && $this->hasPermission('returns.create')) {
            return in_array($action, ['read', 'update_status', 'scan'], true);
        }

        if ($this->canUpdateReturnStatus() || $this->hasPermission('returns.create')) {
            return in_array($action, ['read', 'update_status', 'scan'], true);
        }

        if (! $return && $this->hasPermission('returns.read.own')) {
            return $action === 'read';
        }

        return false;
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        $fullName = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $fullName !== '' ? $fullName : (string) $this->name;
    }

    /**
     * Path of the stored profile photo (Jetstream or user-management upload).
     */
    public function profilePhotoPath(): ?string
    {
        return $this->profile_photo_path ?: $this->photo;
    }

    /**
     * Whether the user has uploaded a custom profile photo.
     */
    public function hasProfilePhoto(): bool
    {
        return (bool) $this->profilePhotoPath();
    }

    /**
     * Public URL for a file on the public disk (relative, works regardless of APP_URL).
     */
    public static function publicStorageUrl(string $path): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Override Jetstream accessor: unify profile_photo_path and photo, use relative URLs.
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $path = $this->profilePhotoPath();

            return $path
                ? self::publicStorageUrl($path)
                : $this->defaultProfilePhotoUrl();
        });
    }

    /**
     * Custom photo URL (null when no uploaded photo — for UIs that show their own fallback).
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $path = $this->profilePhotoPath();

        return $path ? self::publicStorageUrl($path) : null;
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
                'url' => isset($file['path']) ? self::publicStorageUrl($file['path']) : '',
            ])
            ->values()
            ->all();
    }

    public function getRibAttachmentUrlAttribute(): ?string
    {
        return $this->rib_attachment ? self::publicStorageUrl($this->rib_attachment) : null;
    }

    public function getCinFrontAttachmentUrlAttribute(): ?string
    {
        return $this->cin_front_attachment ? self::publicStorageUrl($this->cin_front_attachment) : null;
    }

    public function getCinBackAttachmentUrlAttribute(): ?string
    {
        return $this->cin_back_attachment ? self::publicStorageUrl($this->cin_back_attachment) : null;
    }

    /**
     * Whether automatic billing is enabled and currently due for this seller.
     */
    public function isBillingDue(?CarbonInterface $asOf = null): bool
    {
        if (! $this->billing_enabled || ! $this->next_billing_date) {
            return false;
        }

        $frequency = $this->billing_frequency instanceof BillingFrequency
            ? $this->billing_frequency
            : ($this->billing_frequency ? BillingFrequency::from($this->billing_frequency) : null);

        if (! $frequency || ! $frequency->isAutomatic()) {
            return false;
        }

        return $this->next_billing_date->lte($asOf ?? now());
    }
}
