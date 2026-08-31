<?php

use App\Enums\NotificationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\NotificationPermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * One grant per notification topic.
 *
 * Every staff account was hearing every announcement — a shop signing up, a
 * collection being ready, an invoice being issued — because the recipient lists
 * were the only filter and the preference switches all start enabled. Whether
 * an announcement concerns you is a matter of role, so it becomes a permission,
 * seeded here with the same split the matrix documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = [];

        foreach (NotificationType::cases() as $type) {
            $name = NotificationPermissions::for($type);

            $ids[$name] = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => 'notifications',
                    'action' => 'receive',
                    'scope' => $type->value,
                    'type' => 'resource',
                ]
            )->id;
        }

        $grants = [
            ...array_fill_keys(User::SUPER_ADMIN_ROLES, NotificationPermissions::adminDefaults()),
            Role::DISPATCHER => NotificationPermissions::dispatcherDefaults(),
            Role::DRIVER => NotificationPermissions::driverDefaults(),
            Role::SELLER => NotificationPermissions::sellerDefaults(),
        ];

        foreach ($grants as $roleName => $permissions) {
            Role::query()
                ->whereNull('owner_id')
                ->where('name', $roleName)
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching(
                    array_map(static fn (string $name) => $ids[$name], $permissions)
                ));
        }
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', NotificationPermissions::all())->delete();
    }
};
