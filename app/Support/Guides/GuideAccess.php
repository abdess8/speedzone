<?php

namespace App\Support\Guides;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which roles are offered which guide.
 *
 * The catalog says a guide *exists* and which permission it presupposes; this
 * says who is actually invited to it. The two are different questions: a
 * dispatcher may well be allowed to create an order without the training path
 * for it being part of his job, and an administrator has to be able to decide
 * that from the roles screen rather than by editing PHP.
 *
 * A guide nobody is assigned to is **unrestricted**, not hidden. Silence has to
 * mean "no opinion", otherwise adding a guide to the catalog and forgetting the
 * grid would make it invisible to the entire platform with nothing to show for
 * it. The roles screen labels that state so it is never a surprise.
 */
final class GuideAccess
{
    /**
     * guide key → role ids, memoised for the request.
     *
     * @var array<string, array<int, int>>|null
     */
    private static ?array $memo = null;

    /**
     * @return array<string, array<int, int>>
     */
    public static function map(): array
    {
        return self::$memo ??= DB::table('guide_role')
            ->get(['guide_key', 'role_id'])
            ->groupBy('guide_key')
            ->map(fn ($rows) => $rows->pluck('role_id')->map(fn ($id) => (int) $id)->all())
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public static function rolesFor(string $guide): array
    {
        return self::map()[$guide] ?? [];
    }

    /**
     * Drop every memo.
     *
     * Both of them, not just the grid: role ids are memoised too, and a test
     * suite that rebuilds its roles between cases would otherwise keep matching
     * against the ids of a database that no longer exists.
     */
    public static function forget(): void
    {
        self::$memo = null;
        self::$sellerRoleIdMemo = null;
    }

    /**
     * Whether this reader is invited to this guide.
     */
    public static function allows(User $user, string $guide): bool
    {
        $roles = self::rolesFor($guide);

        if ($roles === []) {
            return true;
        }

        return array_intersect($roles, self::effectiveRoleIds($user)) !== [];
    }

    /**
     * The roles that speak for a reader.
     *
     * A vendor's team member holds a custom role that no administrator manages
     * from the platform roles screen, so on its own it would match nothing.
     * Since he works on the vendor side of the application — that is exactly
     * what `isSeller()` answers — he follows the Seller grid.
     *
     * @return array<int, int>
     */
    public static function effectiveRoleIds(User $user): array
    {
        $ids = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($user->isSeller()) {
            $sellerId = self::sellerRoleId();

            if ($sellerId !== null) {
                $ids[] = $sellerId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Replace the whole grid.
     *
     * Written as one transaction over the full matrix rather than per role: the
     * screen edits every pairing at once, and a partial save would leave the
     * grid in a state nobody asked for.
     *
     * @param  array<string, array<int, int>>  $assignments  guide key → role ids
     */
    public static function sync(array $assignments): void
    {
        DB::transaction(function () use ($assignments) {
            $now = now();

            foreach ($assignments as $guide => $roleIds) {
                DB::table('guide_role')->where('guide_key', $guide)->delete();

                $rows = array_map(fn (int $roleId) => [
                    'guide_key' => $guide,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], array_values(array_unique(array_map('intval', $roleIds))));

                if ($rows !== []) {
                    DB::table('guide_role')->insert($rows);
                }
            }
        });

        self::forget();
    }

    private static ?int $sellerRoleIdMemo = null;

    private static function sellerRoleId(): ?int
    {
        if (self::$sellerRoleIdMemo !== null) {
            return self::$sellerRoleIdMemo;
        }

        $id = Role::query()->system()->where('name', Role::SELLER)->value('id');

        return self::$sellerRoleIdMemo = $id !== null ? (int) $id : null;
    }
}
