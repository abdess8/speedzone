<?php

namespace App\Support;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Active users whose role may hear about a topic.
 *
 * The query is the coarse net: topic grant plus the operational permission
 * the object lives behind. {@see \App\Notifications\AppNotification} still
 * drops anybody who cannot see the specific row, or who silenced the topic.
 */
final class NotificationRecipients
{
    /**
     * @param  array<int, string>  $alsoAny  Extra operational grants the listener
     *                                       requires on top of the topic's own
     *                                       resource permissions (e.g. staff-only
     *                                       for "a ticket was opened").
     */
    public static function query(NotificationType $type, array $alsoAny = []): Builder
    {
        $topic = NotificationPermissions::for($type);
        $resources = NotificationPermissions::resourcePermissions($type);

        return User::query()
            ->where('status', UserStatus::Active->value)
            ->where(function (Builder $query) use ($topic, $resources, $alsoAny) {
                $query->whereHas(
                    'roles',
                    fn (Builder $roles) => $roles->whereIn('name', User::SUPER_ADMIN_ROLES)
                )->orWhere(function (Builder $entitled) use ($topic, $resources, $alsoAny) {
                    self::constrainHasPermission($entitled, $topic);

                    if ($resources !== []) {
                        self::constrainHasAnyPermission($entitled, $resources);
                    }

                    if ($alsoAny !== []) {
                        self::constrainHasAnyPermission($entitled, $alsoAny);
                    }
                });
            })
            ->with(['roles.permissions', 'permissions']);
    }

    public static function constrainHasPermission(Builder $query, string $permission): void
    {
        $query->where(function (Builder $inner) use ($permission) {
            $inner->whereHas('roles.permissions', fn (Builder $q) => $q->where('name', $permission))
                ->orWhereHas('permissions', fn (Builder $q) => $q->where('name', $permission));
        });
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public static function constrainHasAnyPermission(Builder $query, array $permissions): void
    {
        $query->where(function (Builder $inner) use ($permissions) {
            $inner->whereHas('roles.permissions', fn (Builder $q) => $q->whereIn('name', $permissions))
                ->orWhereHas('permissions', fn (Builder $q) => $q->whereIn('name', $permissions));
        });
    }
}
