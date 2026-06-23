<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PartnerUserAssignmentService
{
    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_PAGE_SIZE = 25;

    public function query(Request $request): Builder
    {
        $query = Partner::query()
            ->active()
            ->with(['users:id,name,first_name,last_name,email'])
            ->withCount('users')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query;
    }

    public function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PAGE_SIZE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PAGE_SIZE;
        }

        return min($perPage, self::MAX_PAGE_SIZE);
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null}>
     */
    public function adminOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::ADMIN, Role::DISPATCHER]))
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function syncUsers(Partner $partner, array $userIds, bool $replace = false): void
    {
        $validIds = User::query()
            ->whereKey($userIds)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::ADMIN, Role::DISPATCHER]))
            ->pluck('id')
            ->all();

        if ($replace) {
            $partner->users()->sync($validIds);

            return;
        }

        $partner->users()->syncWithoutDetaching($validIds);
    }

    public function removeUser(Partner $partner, User $user): void
    {
        $partner->users()->detach($user->id);
    }
}
