<?php

namespace App\Support;

use App\Enums\BulkStatusEntityType;
use App\Models\Permission;
use Illuminate\Support\Str;

/**
 * Resolves human-readable, translatable labels for permissions shown in the
 * Roles & Permissions UI.
 */
final class PermissionLabels
{
    public static function resourceLabel(string $resource): string
    {
        $key = "permissions.resources.{$resource}";
        $label = __($key);

        return $label !== $key ? $label : Str::headline(str_replace('_', ' ', $resource));
    }

    public static function permissionLabel(Permission $permission): string
    {
        $key = "permissions.names.{$permission->name}";
        $label = __($key);

        if ($label !== $key) {
            return $label;
        }

        if ($permission->type === 'workflow_transition') {
            $target = Str::of($permission->name)->afterLast('to_')->replace('_', ' ')->title();

            return 'Transition → '.$target;
        }

        // A bulk-edit grant names both ends, and both have to show: "→ Livré"
        // would be indistinguishable from the workflow grant above, while the
        // whole point of these is the route taken to get there.
        if ($permission->type === StatusTransitionPermissions::TYPE) {
            return self::transitionPairLabel($permission);
        }

        $text = Str::headline(str_replace('_', ' ', $permission->action));

        if ($permission->scope) {
            $text .= ' ('.Str::title($permission->scope).')';
        }

        return $text;
    }

    /**
     * "En ville de livraison → Livré", read from the status enums so the Roles
     * screen speaks the same vocabulary as the parcel screens.
     */
    private static function transitionPairLabel(Permission $permission): string
    {
        $entity = $permission->resource === 'returns'
            ? BulkStatusEntityType::RETURN
            : BulkStatusEntityType::ORDER;

        $pair = Str::of($permission->name)
            ->after('.'.StatusTransitionPermissions::ACTION.'.')
            ->explode('.');

        if ($pair->count() !== 2) {
            return $permission->name;
        }

        [$from, $to] = [strtoupper($pair[0]), strtoupper($pair[1])];

        return $entity->statusLabel($from).' → '.$entity->statusLabel($to);
    }

    public static function permissionDescription(Permission $permission): ?string
    {
        $key = "permissions.descriptions.{$permission->name}";
        $description = __($key);

        return $description !== $key ? $description : null;
    }
}
