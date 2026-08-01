<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Events\NewSellerRegistered;
use App\Events\SellerApproved;
use App\Events\SellerRejected;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionLabels;
use App\Support\SellerRegistrationPermissions;
use Illuminate\Support\Facades\DB;

class SellerApprovalService
{
    public function __construct(private readonly StoreService $stores) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupedSellerPermissions(): array
    {
        return Permission::query()
            ->whereIn('name', SellerRegistrationPermissions::assignable())
            ->orderBy('resource')
            ->orderBy('name')
            ->get()
            ->groupBy('resource')
            ->map(function ($permissions, $resource) {
                return [
                    'resource' => $resource,
                    'label' => PermissionLabels::resourceLabel((string) $resource),
                    'permissions' => $permissions->map(fn (Permission $permission) => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'action' => $permission->action,
                        'scope' => $permission->scope,
                        'type' => $permission->type,
                        'label' => PermissionLabels::permissionLabel($permission),
                        'description' => PermissionLabels::permissionDescription($permission),
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function defaultPermissionIds(): array
    {
        return Permission::query()
            ->whereIn('name', SellerRegistrationPermissions::defaults())
            ->pluck('id')
            ->all();
    }

    public function approve(User $user, User $admin, array $permissionIds): User
    {
        return DB::transaction(function () use ($user, $admin, $permissionIds) {
            $user->forceFill([
                'status' => UserStatus::Active,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejection_reason' => null,
            ])->save();

            $user->permissions()->sync($permissionIds);

            // Every active vendor needs at least one shop: without it there is
            // no store to attach his orders to and the isolation scope has
            // nothing to enforce.
            $this->stores->createDefaultFor($user);

            SellerApproved::dispatch($user->fresh(['city', 'permissions']));

            return $user;
        });
    }

    public function reject(User $user, User $admin, ?string $reason = null): User
    {
        return DB::transaction(function () use ($user, $admin, $reason) {
            $user->forceFill([
                'status' => UserStatus::Rejected,
                'approved_at' => null,
                'approved_by' => $admin->id,
                'rejection_reason' => $reason,
            ])->save();

            $user->permissions()->detach();

            SellerRejected::dispatch($user->fresh(['city']), $reason);

            return $user;
        });
    }

    public function adminCanReview(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->hasRolePermission('roles.read');
    }

    public function isPendingSeller(User $user): bool
    {
        return $user->isSeller()
            && in_array($user->status, [UserStatus::PendingApproval, UserStatus::PendingEmailVerification], true);
    }
}
