<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which roles are offered which guide, at the time this migration ran.
     *
     * Hard-coded rather than read from `GuideCatalog`: a migration is a
     * snapshot, and it must still run years from now against a catalog that has
     * moved on. The permissions listed here are only used to *derive* the
     * starting assignment — from here on the pairing is data, edited from the
     * roles screen.
     *
     * @var array<string, array<int, string>>
     */
    private const SEED = [
        'orders-import' => ['orders.create'],
        'orders-create' => ['orders.create'],
        'stores-manage' => ['stores.read'],
        'team-member' => ['team.read'],
        'pickups-create' => ['pickup_requests.create'],
        'invoices-read' => ['invoices.read.own', 'invoices.read.all'],
        'returns-request' => ['returns.create_request'],
    ];

    public function up(): void
    {
        Schema::create('guide_role', function (Blueprint $table) {
            $table->id();

            // Guides are code, not rows: no foreign key, and a guide that is
            // retired leaves a harmless orphan instead of a failed migration.
            $table->string('guide_key', 64);
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['guide_key', 'role_id']);
        });

        $this->seedFromPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_role');
    }

    /**
     * Start every role off with the guides it could already follow.
     *
     * Without this the screen would open with an empty grid and every guide
     * would silently fall back to "unrestricted", which is a confusing first
     * impression of a feature whose whole point is to be explicit.
     */
    private function seedFromPermissions(): void
    {
        $now = now();
        $rows = [];

        foreach (self::SEED as $guide => $permissions) {
            $roleIds = DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                // Platform roles only: vendor roles inherit the Seller grid.
                ->whereNull('roles.owner_id')
                ->whereIn('permissions.name', $permissions)
                ->distinct()
                ->pluck('permission_role.role_id');

            foreach ($roleIds as $roleId) {
                $rows[] = [
                    'guide_key' => $guide,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('guide_role')->insert($chunk);
        }
    }
};
