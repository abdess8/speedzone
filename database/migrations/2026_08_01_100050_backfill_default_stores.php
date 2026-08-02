<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every existing seller a default store and attach his history to it.
     *
     * Without this, the store global scope would filter every legacy row out of
     * sight the moment it is enabled: the seller would open his order list and
     * find it empty.
     *
     * Written against the query builder rather than the models so it stays
     * correct even after the models evolve.
     *
     * Owner column per table; `returns` is handled separately through its order.
     *
     * @var array<string, string>
     */
    private const OWNER_COLUMNS = [
        'orders' => 'seller_id',
        'invoices' => 'seller_id',
        'pickup_requests' => 'created_by',
        'support_tickets' => 'created_by',
    ];

    public function up(): void
    {
        $now = now();

        $sellers = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', Role::SELLER)
            ->whereNull('users.parent_user_id')
            ->select('users.id', 'users.name', 'users.first_name', 'users.last_name')
            ->addSelect('users.default_store_name', 'users.default_store_logo')
            ->addSelect('users.city_id', 'users.address', 'users.pickup_address_1', 'users.pickup_address_2')
            ->addSelect('users.phone_number', 'users.email')
            ->distinct()
            ->get();

        foreach ($sellers as $seller) {
            if (DB::table('stores')->where('owner_id', $seller->id)->exists()) {
                continue;
            }

            $storeId = DB::table('stores')->insertGetId([
                'owner_id' => $seller->id,
                'name' => $this->storeName($seller),
                'logo_path' => $seller->default_store_logo,
                'contact_name' => trim(($seller->first_name ?? '').' '.($seller->last_name ?? '')) ?: $seller->name,
                'contact_phone' => $seller->phone_number,
                'contact_email' => $seller->email,
                'city_id' => $seller->city_id,
                'address' => $seller->address,
                'pickup_address_1' => $seller->pickup_address_1,
                'pickup_address_2' => $seller->pickup_address_2,
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('store_user')->insert([
                'store_id' => $storeId,
                'user_id' => $seller->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::OWNER_COLUMNS as $table => $ownerColumn) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)
                    ->where($ownerColumn, $seller->id)
                    ->whereNull('store_id')
                    ->update(['store_id' => $storeId]);
            }
        }

        $this->backfillReturns();
    }

    /**
     * Returns inherit the store of the order they reverse.
     */
    private function backfillReturns(): void
    {
        if (! Schema::hasTable('returns')) {
            return;
        }

        DB::table('returns')
            ->join('orders', 'orders.id', '=', 'returns.order_id')
            ->whereNull('returns.store_id')
            ->whereNotNull('orders.store_id')
            ->update(['returns.store_id' => DB::raw('orders.store_id')]);
    }

    private function storeName(object $seller): string
    {
        $name = trim((string) ($seller->default_store_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $fullName = trim(($seller->first_name ?? '').' '.($seller->last_name ?? ''));

        return $fullName !== '' ? $fullName : (string) $seller->name;
    }

    public function down(): void
    {
        foreach (array_keys(self::OWNER_COLUMNS) as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->update(['store_id' => null]);
            }
        }

        if (Schema::hasTable('returns')) {
            DB::table('returns')->update(['store_id' => null]);
        }

        DB::table('store_user')->delete();
        DB::table('stores')->delete();
    }
};
