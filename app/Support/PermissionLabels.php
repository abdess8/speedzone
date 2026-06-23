<?php

namespace App\Support;

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

        $text = Str::headline(str_replace('_', ' ', $permission->action));

        if ($permission->scope) {
            $text .= ' ('.Str::title($permission->scope).')';
        }

        return $text;
    }

    public static function permissionDescription(Permission $permission): ?string
    {
        $key = "permissions.descriptions.{$permission->name}";
        $description = __($key);

        return $description !== $key ? $description : null;
    }
}
