<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\EcommerceIntegrationPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Storefront integration grants.
     *
     * Seeded to the Seller role only: connecting a Shopify or YouCan shop is a
     * vendor-side action on the vendor's own store. Team roles inherit nothing
     * here — the owner delegates it explicitly, which is the whole point of the
     * split.
     */
    public function up(): void
    {
        $ids = [];

        foreach (EcommerceIntegrationPermissions::all() as $name) {
            $ids[] = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => 'integrations',
                    'action' => str_contains($name, 'manage') ? 'manage' : 'read',
                    'scope' => null,
                    'type' => 'resource',
                ]
            )->id;
        }

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', [...User::SUPER_ADMIN_ROLES, Role::SELLER])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', EcommerceIntegrationPermissions::all())
            ->delete();
    }
};
