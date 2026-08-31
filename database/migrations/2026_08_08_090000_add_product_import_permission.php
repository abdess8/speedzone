<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\StockPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Splits the bulk import out of `stock.create_product`.
     *
     * Existing installs are not silently narrowed: every role that already held
     * `stock.create_product` keeps the wizard, and vendor roles are included so
     * a team member who was importing yesterday still can today. Withdrawing it
     * from someone is then a deliberate act in the role editor.
     */
    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['name' => StockPermissions::IMPORT_PRODUCTS],
            [
                'resource' => 'stock',
                'action' => 'import_products',
                'scope' => null,
                'type' => 'resource',
            ]
        );

        $createProductId = Permission::query()
            ->where('name', StockPermissions::CREATE_PRODUCT)
            ->value('id');

        Role::query()
            ->when(
                $createProductId,
                fn ($query) => $query->where(
                    fn ($q) => $q
                        ->whereIn('name', User::SUPER_ADMIN_ROLES)
                        ->orWhereHas('permissions', fn ($p) => $p->whereKey($createProductId))
                ),
                fn ($query) => $query->whereIn('name', User::SUPER_ADMIN_ROLES)
            )
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }

    public function down(): void
    {
        Permission::query()->where('name', StockPermissions::IMPORT_PRODUCTS)->delete();
    }
};
