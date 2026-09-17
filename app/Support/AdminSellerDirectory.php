<?php

namespace App\Support;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Vendor accounts a super admin can attach shops, team members and connectors to.
 *
 * A seller is an account owner (no `parent_user_id`) who either holds the
 * platform Seller role or already owns at least one store. Team members and
 * staff roles stay out of the picker so an admin cannot accidentally hang a
 * shop off a dispatcher or an employee.
 */
final class AdminSellerDirectory
{
    /**
     * @return Builder<User>
     */
    public static function query(): Builder
    {
        return User::query()
            ->whereNull('parent_user_id')
            ->where(function (Builder $query) {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', Role::SELLER))
                    ->orWhereHas('ownedStores');
            })
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    /**
     * @return Collection<int, User>
     */
    public static function all(): Collection
    {
        return self::query()->get(['id', 'first_name', 'last_name', 'email']);
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    public static function options(): array
    {
        return self::all()
            ->map(fn (User $seller) => self::present($seller))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, email: string}
     */
    public static function present(User $seller): array
    {
        return [
            'id' => $seller->id,
            'name' => $seller->full_name,
            'email' => $seller->email,
        ];
    }

    public static function find(int $id): ?User
    {
        if ($id < 1) {
            return null;
        }

        return self::query()->whereKey($id)->first();
    }

    public static function findOrFail(int $id): User
    {
        $seller = self::find($id);

        abort_unless($seller, 404);

        return $seller;
    }

    /**
     * The vendor an admin action should write against.
     *
     * Sellers always act as themselves. Super admins never do: a create uses
     * `seller_id`, an update uses the row's owner so a tampered payload cannot
     * re-parent a shop or a teammate onto another account.
     */
    public static function actingOwner(
        Request $request,
        Store|User|Role|null $subject = null,
    ): User {
        $actor = $request->user();

        if (! $actor->isSuperAdmin()) {
            return $actor;
        }

        if ($subject instanceof Store) {
            return $subject->owner()->firstOrFail();
        }

        if ($subject instanceof Role && $subject->owner_id) {
            return $subject->owner()->firstOrFail();
        }

        if ($subject instanceof User && $subject->parent_user_id) {
            return User::query()->findOrFail($subject->parent_user_id);
        }

        return self::findOrFail($request->integer('seller_id'));
    }
}
