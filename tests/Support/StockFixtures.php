<?php

namespace Tests\Support;

use App\Enums\StockReceptionStatus;
use App\Models\City;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockReception;
use App\Models\StockReceptionItem;
use App\Models\Store;
use App\Models\User;
use App\Services\StockReceptionService;
use Tests\TestCase;

/**
 * Fixtures shared by the stock module test files.
 *
 * A class rather than the free functions the older suites use, so each stock
 * test file still runs on its own instead of depending on whichever sibling
 * happened to declare the helper first.
 */
final class StockFixtures
{
    /**
     * An actor holding exactly the grants its role carries.
     *
     * @param  User|null  $employer  Set to make the user a team member of a vendor account.
     */
    public static function user(string $roleName, ?User $employer = null): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        $user = User::factory()->create([
            'role_id' => $role->id,
            'parent_user_id' => $employer?->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    /**
     * The city our depot stands in.
     *
     * Shared by every fixture shop, since one warehouse serving many vendors is
     * exactly the arrangement the module exists for.
     */
    public static function hubCity(): City
    {
        return City::query()->firstOrCreate(
            ['code' => 'HUB'],
            [
                'name' => 'Hub City',
                'region' => 'Test',
                'is_active' => true,
                'is_stock_hub' => true,
            ]
        );
    }

    /**
     * The city a fixture shop trades from, which is where collections happen.
     *
     * Distinct from the depot city by default: a vendor shipping stock to a
     * warehouse in his own street is the case least likely to catch a routing bug.
     */
    public static function shopCity(): City
    {
        return City::query()->firstOrCreate(
            ['code' => 'SHOP'],
            [
                'name' => 'Shop City',
                'region' => 'Test',
                'is_active' => true,
                'is_stock_hub' => false,
            ]
        );
    }

    /**
     * A city nobody in these fixtures works.
     *
     * Exists so the routing rules can be tested from the outside: a collector placed
     * here must neither be called for a round nor allowed to sign for one.
     */
    public static function farCity(): City
    {
        return City::query()->firstOrCreate(
            ['code' => 'FAR'],
            [
                'name' => 'Far City',
                'region' => 'Test',
                'is_active' => true,
                'is_stock_hub' => false,
            ]
        );
    }

    /**
     * A shop that warehouses with us.
     *
     * The depot is set by default because that is the ordinary state of a shop
     * using this module; a shop still without one is a first-shipment edge case
     * and is set up explicitly by the tests that care.
     *
     * @param  array<int, int>  $members  Extra user ids granted access to the shop.
     */
    public static function store(
        User $owner,
        string $name = 'Vendor Shop',
        array $members = [],
        ?City $depot = null,
        ?City $city = null,
    ): Store {
        $store = Store::query()->create([
            'owner_id' => $owner->id,
            'name' => $name,
            'is_default' => ! Store::query()->where('owner_id', $owner->id)->exists(),
            'is_active' => true,
            'city_id' => ($city ?? self::shopCity())->id,
            'stock_hub_city_id' => ($depot ?? self::hubCity())->id,
        ]);

        $store->users()->syncWithoutDetaching([$owner->id, ...$members]);

        return $store;
    }

    /**
     * A collector working a given city.
     *
     * The city is written on the profile rather than through a sector, because the
     * fallback path is the one every fixture shares and the sector path is what the
     * routing tests set up explicitly.
     */
    public static function collector(?City $city = null): User
    {
        $collector = self::user(Role::DRIVER);
        $collector->forceFill(['city_id' => ($city ?? self::shopCity())->id])->save();

        return $collector->fresh(['roles.permissions']);
    }

    /**
     * A shipment the vendor has asked us to come and get.
     *
     * Declared over HTTP rather than through the service, because the request is
     * what puts the shop context in place — and what fires the pickup announcement
     * the collection tests read back.
     *
     * @param  array<int, array{product_id: int, quantity_sent: int}>  $lines
     */
    public static function awaitingPickup(TestCase $test, User $seller, array $lines): StockReception
    {
        $test->actingAs($seller)
            ->post('/stock-receptions', [
                'status' => StockReceptionStatus::AWAITING_PICKUP->value,
                'items' => $lines,
            ])
            ->assertSessionHasNoErrors();

        return StockReception::acrossStores()->latest('id')->firstOrFail();
    }

    /**
     * Walk a shipment to the depot's door.
     *
     * The depot can only count a parcel that is on the road, so any test about the
     * arrival has to get it collected and dispatched first. Done through the service
     * rather than by stamping the status, so the collected quantities the depot is
     * compared against are the ones the real flow would have written.
     */
    public static function readyForDepot(StockReception $reception, ?User $collector = null): StockReception
    {
        $collector ??= self::collector();
        $service = app(StockReceptionService::class);

        $lines = $reception->items()->get()->map(fn (StockReceptionItem $item) => [
            'id' => $item->id,
            'quantity_collected' => (int) $item->quantity_sent,
        ])->all();

        $service->collect($reception, $lines, $collector);

        return $service->dispatchToDepot($reception->refresh(), $collector);
    }

    /**
     * Plant a catalog reference.
     *
     * Written through the unscoped query on purpose, so a fixture can belong to
     * a shop the actor is not standing on.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function product(User $seller, Store $store, array $attributes = []): Product
    {
        // Guarded rather than fillable in production, because only the ledger may
        // move it; a fixture stating an opening quantity has to write it directly.
        $quantity = (int) ($attributes['stock_quantity'] ?? 0);
        unset($attributes['stock_quantity']);

        $product = Product::acrossStores()->create(array_merge([
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'name' => 'Cotton T-shirt',
            'sku' => 'SKU-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'unit_price' => 180,
            'cost_price' => 90,
            'is_active' => true,
        ], $attributes));

        if ($quantity !== 0) {
            $product->forceFill(['stock_quantity' => $quantity])->save();
        }

        return $product;
    }
}
