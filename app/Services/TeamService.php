<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Team members of a vendor account.
 *
 * A member is a real user row with `parent_user_id` pointing at the vendor, so
 * every existing `.own` scope keeps working through User::accountOwnerId(),
 * and the store pivot narrows him further to the shops he was granted.
 */
class TeamService
{
    public function __construct(private readonly UserSessionService $sessions) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $owner, array $data): User
    {
        $storeIds = $this->ownedStoreIds($owner, $data['store_ids'] ?? []);
        $roleIds = $this->ownedRoleIds($owner, $data['role_ids'] ?? []);

        return DB::transaction(function () use ($owner, $data, $storeIds, $roleIds) {
            $member = User::create([
                'parent_user_id' => $owner->id,
                'status' => UserStatus::Active->value,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? null,
                'password' => Hash::make($data['password']),
                'locale' => $data['locale'] ?? $owner->locale,
                'city_id' => $owner->city_id,
                // Legacy single-role column, kept in sync so the admin screens
                // still show something meaningful for the member.
                'role_id' => $roleIds[0] ?? null,
            ]);

            // The vendor vouches for the member and sets his password himself,
            // so there is no verification mail to wait for. forceFill because
            // `email_verified_at` is deliberately kept out of $fillable.
            $member->forceFill(['email_verified_at' => now()])->save();

            $member->roles()->sync($roleIds);
            $member->stores()->sync($storeIds);

            return $member;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $owner, User $member, array $data): User
    {
        $this->assertMemberOf($owner, $member);

        $storeIds = $this->ownedStoreIds($owner, $data['store_ids'] ?? []);
        $roleIds = $this->ownedRoleIds($owner, $data['role_ids'] ?? []);

        return DB::transaction(function () use ($member, $data, $storeIds, $roleIds) {
            $attributes = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? null,
                'role_id' => $roleIds[0] ?? null,
            ];

            if (! empty($data['password'])) {
                $attributes['password'] = Hash::make($data['password']);
            }

            $member->update($attributes);
            $member->roles()->sync($roleIds);
            $member->stores()->sync($storeIds);

            if (! empty($data['password'])) {
                // A password reset by the vendor is a takeover: existing
                // sessions must not survive it.
                $this->sessions->revokeAll($member);
            }

            $member->forgetStoreMemo();

            return $member->fresh(['roles', 'stores']);
        });
    }

    /**
     * Revoke access and kick the member out of any open tab.
     *
     * @return int Number of sessions destroyed.
     */
    public function suspend(User $owner, User $member): int
    {
        $this->assertMemberOf($owner, $member);

        $member->update(['status' => UserStatus::Suspended->value]);

        return $this->sessions->revokeAll($member);
    }

    public function reactivate(User $owner, User $member): User
    {
        $this->assertMemberOf($owner, $member);

        $member->update(['status' => UserStatus::Active->value]);

        return $member;
    }

    /**
     * @return Collection<int, User>
     */
    public function membersOf(User $owner): Collection
    {
        return $owner->teamMembers()
            ->with(['roles:id,name,label', 'stores:id,name'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * A vendor may only ever touch his own members.
     */
    private function assertMemberOf(User $owner, User $member): void
    {
        if ($member->parent_user_id !== $owner->id) {
            throw ValidationException::withMessages([
                'member' => __('team.errors.not_a_member'),
            ]);
        }
    }

    /**
     * Keep only the stores the vendor actually owns.
     *
     * @param  array<int, int|string>  $storeIds
     * @return array<int, int>
     */
    private function ownedStoreIds(User $owner, array $storeIds): array
    {
        if ($storeIds === []) {
            throw ValidationException::withMessages([
                'store_ids' => __('team.errors.store_required'),
            ]);
        }

        return Store::query()
            ->ownedBy($owner->id)
            ->whereIn('id', array_map('intval', $storeIds))
            ->pluck('id')
            ->all();
    }

    /**
     * Keep only the custom roles the vendor defined.
     *
     * A member never receives the platform Seller role: that would hand him the
     * full seller permission set, store administration included.
     *
     * @param  array<int, int|string>  $roleIds
     * @return array<int, int>
     */
    private function ownedRoleIds(User $owner, array $roleIds): array
    {
        if ($roleIds === []) {
            throw ValidationException::withMessages([
                'role_ids' => __('team.errors.role_required'),
            ]);
        }

        return Role::query()
            ->ownedBy($owner->id)
            ->whereIn('id', array_map('intval', $roleIds))
            ->pluck('id')
            ->all();
    }
}
