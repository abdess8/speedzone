<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Str;

/**
 * Human readable label for a role, resolved from its internal name.
 *
 * Platform roles (Admin, Dispatcher, …) are translated through `roles.php`.
 * Vendor roles cannot be: their name is namespaced (`vendor.8.preparateur-colis`)
 * so `trans()` would read the dots as nested keys and hand back the raw key,
 * which is exactly what used to leak into the topbar and the user list. Their
 * label lives in the `label` column instead.
 */
class RoleLabel
{
    /**
     * Labels already read from the database, keyed by role name.
     *
     * @var array<string, string>
     */
    private static array $memo = [];

    /**
     * Label for a role name, or null when there is no role.
     */
    public static function for(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (Str::startsWith($name, Role::VENDOR_PREFIX)) {
            return self::vendorLabel($name);
        }

        $key = 'roles.'.$name;
        $translated = trans($key);

        return is_string($translated) && $translated !== $key ? $translated : $name;
    }

    /**
     * Same, for a role that is already in memory — no extra query.
     */
    public static function of(?Role $role): ?string
    {
        if (! $role) {
            return null;
        }

        return $role->label ?: self::for($role->name);
    }

    /**
     * Drop the memoized labels (renaming a role within the same process).
     */
    public static function flush(): void
    {
        self::$memo = [];
    }

    /**
     * The label stored on a vendor role, falling back to a readable form of the
     * slug so a deleted or unreadable role still renders as words.
     */
    private static function vendorLabel(string $name): string
    {
        if (! array_key_exists($name, self::$memo)) {
            $label = Role::query()->where('name', $name)->value('label');

            self::$memo[$name] = $label ?: Str::headline(Str::afterLast($name, '.'));
        }

        return self::$memo[$name];
    }
}
