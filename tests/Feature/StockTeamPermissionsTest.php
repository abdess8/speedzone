<?php

use App\Enums\PaymentMethod;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockReceptionStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\StockReception;
use App\Models\User;
use App\Services\TeamRoleService;
use App\Services\TeamService;
use App\Support\StockPermissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Support\StockFixtures;

/**
 * What a vendor owner may hand to his team, and what he may not.
 *
 * The five vendor grants are delegatable one by one, so a stock keeper can count
 * shelves without being able to open references or price them. The two hub
 * grants are outside the ceiling entirely: they describe our operations on the
 * vendor's goods.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

/**
 * A team member holding exactly the stock grants listed.
 *
 * @param  array<int, string>  $permissions
 */
function stockTeamMember(User $owner, array $permissions): User
{
    $role = app(TeamRoleService::class)->create($owner, 'Stock keeper', $permissions);

    $member = app(TeamService::class)->create($owner, [
        'first_name' => 'Yassine',
        'last_name' => 'B.',
        'email' => 'member-'.uniqid().'@example.test',
        'password' => 'Secret!12345',
        'store_ids' => $owner->stores()->pluck('stores.id')->all(),
        'role_ids' => [$role->id],
    ]);

    return $member->fresh(['roles.permissions', 'stores']);
}

test('a vendor owner holds every delegatable stock grant', function () {
    $owner = StockFixtures::user(Role::SELLER);

    foreach (StockPermissions::sellerDefaults() as $permission) {
        expect($owner->hasPermission($permission))->toBeTrue();
    }

    expect($owner->hasPermission(StockPermissions::RECEIVE_INBOUND))->toBeFalse()
        ->and($owner->hasPermission(StockPermissions::ADMIN_OVERRIDE))->toBeFalse();
});

test('the hub grants are never delegatable to a team member', function () {
    $owner = StockFixtures::user(Role::SELLER);
    StockFixtures::store($owner);

    $member = stockTeamMember($owner, [
        StockPermissions::VIEW,
        StockPermissions::RECEIVE_INBOUND,
        StockPermissions::ADMIN_OVERRIDE,
    ]);

    expect($member->hasPermission(StockPermissions::VIEW))->toBeTrue()
        ->and($member->hasPermission(StockPermissions::RECEIVE_INBOUND))->toBeFalse()
        ->and($member->hasPermission(StockPermissions::ADMIN_OVERRIDE))->toBeFalse();
});

test('a member granted read only sees the catalog but cannot touch it', function () {
    $owner = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($owner);
    $product = StockFixtures::product($owner, $store, ['stock_quantity' => 10]);

    $member = stockTeamMember($owner, [StockPermissions::VIEW]);

    $this->actingAs($member)->get('/products')->assertOk();
    $this->actingAs($member)->get("/products/{$product->id}")->assertOk();

    $this->actingAs($member)->get('/products/create')->assertForbidden();
    $this->actingAs($member)
        ->put("/products/{$product->id}", ['name' => 'Renamed', 'unit_price' => 10])
        ->assertForbidden();

    expect($product->fresh()->name)->not->toBe('Renamed');
});

test('a member granted counting rights may correct stock but not prices', function () {
    $owner = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($owner);
    $product = StockFixtures::product($owner, $store, ['stock_quantity' => 10]);

    $member = stockTeamMember($owner, [StockPermissions::VIEW, StockPermissions::ADJUST]);

    $this->actingAs($member)
        ->post('/stock/inventory', [
            'adjustments' => [[
                'product_id' => $product->id,
                'counted_quantity' => 7,
                'reason' => StockAdjustmentReason::COUNT_ERROR->value,
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->stock_quantity)->toBe(7);

    $this->actingAs($member)
        ->put("/products/{$product->id}", ['name' => 'Renamed', 'unit_price' => 999])
        ->assertForbidden();
});

test('a member without counting rights cannot correct stock', function () {
    $owner = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($owner);
    $product = StockFixtures::product($owner, $store, ['stock_quantity' => 10]);

    $member = stockTeamMember($owner, [StockPermissions::VIEW]);

    $this->actingAs($member)
        ->post('/stock/inventory', [
            'adjustments' => [[
                'product_id' => $product->id,
                'counted_quantity' => 2,
                'reason' => StockAdjustmentReason::COUNT_ERROR->value,
            ]],
        ])
        ->assertForbidden();

    expect($product->fresh()->stock_quantity)->toBe(10);
});

test('a member granted inbound rights declares a shipment on his employer behalf', function () {
    $owner = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($owner);
    $product = StockFixtures::product($owner, $store);

    $member = stockTeamMember($owner, [StockPermissions::VIEW, StockPermissions::CREATE_INBOUND]);

    $this->actingAs($member)
        ->post('/stock-receptions', [
            'status' => StockReceptionStatus::AWAITING_PICKUP->value,
            'items' => [['product_id' => $product->id, 'quantity_sent' => 15]],
        ])
        ->assertSessionHasNoErrors();

    $reception = StockReception::acrossStores()->latest('id')->firstOrFail();

    // Owned by the employer, traced to the member: the shipment is the vendor
    // account's document, but we still know who filled it in.
    expect($reception->seller_id)->toBe($owner->id)
        ->and($reception->sent_by)->toBe($member->id);
});

test('a member cannot build an order from stock without the picking grant', function () {
    $owner = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($owner);
    $product = StockFixtures::product($owner, $store, ['stock_quantity' => 10]);

    $city = City::query()->create([
        'name' => 'Team City', 'code' => 'TMC', 'region' => 'Test', 'is_active' => true,
    ]);
    $sector = Sector::query()->create([
        'city_id' => $city->id, 'name' => 'Team Sector', 'delivery_price' => 25, 'is_active' => true,
    ]);

    // Everything needed to place a free-amount order, and nothing that unlocks
    // the catalog: `orders.create_with_stock` is the grant under test.
    $member = stockTeamMember($owner, [
        StockPermissions::VIEW,
        'orders.create',
        'orders.read.own',
    ]);

    $this->actingAs($member)
        ->post('/orders', [
            'customer_first_name' => 'Amina',
            'customer_last_name' => 'Bennani',
            'customer_phone' => '0611111111',
            'customer_address' => '5 rue des Fleurs',
            'city_id' => $city->id,
            'sector_id' => $sector->id,
            'payment_method' => PaymentMethod::CASH->value,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSessionHasErrors('items');

    expect($product->fresh()->stock_quantity)->toBe(10)
        ->and(Order::acrossStores()->count())->toBe(0);
});

test('a member granted the picking grant builds an order from stock', function () {
    $owner = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($owner);
    $product = StockFixtures::product($owner, $store, ['unit_price' => 120, 'stock_quantity' => 10]);

    $city = City::query()->create([
        'name' => 'Team City', 'code' => 'TMC', 'region' => 'Test', 'is_active' => true,
    ]);
    $sector = Sector::query()->create([
        'city_id' => $city->id, 'name' => 'Team Sector', 'delivery_price' => 25, 'is_active' => true,
    ]);

    $member = stockTeamMember($owner, [
        StockPermissions::VIEW,
        StockPermissions::ORDERS_CREATE_WITH_STOCK,
        'orders.create',
        'orders.read.own',
    ]);

    $this->actingAs($member)
        ->post('/orders', [
            'customer_first_name' => 'Amina',
            'customer_last_name' => 'Bennani',
            'customer_phone' => '0611111111',
            'customer_address' => '5 rue des Fleurs',
            'city_id' => $city->id,
            'sector_id' => $sector->id,
            'payment_method' => PaymentMethod::CASH->value,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSessionHasNoErrors();

    $order = Order::acrossStores()->latest('id')->firstOrFail();

    expect((float) $order->order_amount)->toBe(240.0)
        ->and($product->fresh()->stock_quantity)->toBe(8);
});
