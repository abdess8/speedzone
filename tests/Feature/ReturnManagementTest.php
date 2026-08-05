<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\ReturnHandBackService;
use App\Services\ReturnService;
use App\Services\ReturnTransitionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function returnTestCity(): City
{
    return City::query()->create([
        'name' => 'Return City',
        'code' => 'RTN',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function returnTestUser(string $roleName, ?City $city = null): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id, 'city_id' => $city?->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

/**
 * Walk a fresh return up to the vendor hub, which is where the hand-back rules
 * this suite cares about start applying.
 */
function returnAtVendorHub(User $seller, User $driver, User $staff, City $city)
{
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    $transitions = app(ReturnTransitionService::class);
    $transitions->receiveAtHub($return->fresh(), $staff);
    $transitions->transition($return->fresh(), ReturnStatus::IN_TRANSIT_TO_DEPOT, $staff);
    $transitions->transition($return->fresh(), ReturnStatus::ARRIVED_VENDOR_HUB, $staff);

    return $return->fresh();
}

function returnTestOrder(User $seller, City $city, OrderStatus $status): Order
{
    return Order::query()->create([
        'tracking_number' => 'RTT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Customer',
        'customer_phone' => '0611111111',
        'customer_address' => '456 Return Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => $status->value,
    ]);
}

test('driver opens the return and the hub signs it in', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $driver = returnTestUser(Role::DRIVER);
    $hub = returnTestUser(Role::DISPATCHER);
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::RETURN_REQUESTED)
        ->and($return->status)->toBe(ReturnStatus::CREATED)
        ->and($order->return_id)->toBe($return->id);

    app(ReturnTransitionService::class)->receiveAtHub($return->fresh(), $hub);

    $order->refresh();
    $return->refresh();

    expect($order->status)->toBe(OrderStatus::RETURN_IN_PROGRESS)
        ->and($return->status)->toBe(ReturnStatus::RECEIVED_AT_HUB)
        ->and($return->statusHistories()->count())->toBeGreaterThan(1);
});

test('a driver may not sign a return into the hub', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $driver = returnTestUser(Role::DRIVER);
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    app(ReturnTransitionService::class)->receiveAtHub($return->fresh(), $driver);
})->throws(AuthorizationException::class);

test('the return walks the six steps back to the vendor', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER, $city);
    $driver = returnTestUser(Role::DRIVER, $city);
    $admin = returnTestUser(Role::ADMIN);

    $return = returnAtVendorHub($seller, $driver, $admin, $city);

    $transitions = app(ReturnTransitionService::class);
    $transitions->handBack($return, $admin, $driver);
    $transitions->transition($return->fresh(), ReturnStatus::DELIVERED_TO_VENDOR, $driver);

    $order = $return->order->fresh();

    expect($return->fresh()->status)->toBe(ReturnStatus::DELIVERED_TO_VENDOR)
        ->and($order->status)->toBe(OrderStatus::RETURNED)
        ->and($order->is_returned)->toBeTrue()
        ->and($order->returned_at)->not->toBeNull();
});

test('the return workflow refuses to skip a step', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $driver = returnTestUser(Role::DRIVER);
    $admin = returnTestUser(Role::ADMIN);
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    app(ReturnTransitionService::class)
        ->transition($return->fresh(), ReturnStatus::ARRIVED_VENDOR_HUB, $admin);
})->throws(ValidationException::class);

test('a return failed in the sellers own city skips the transfer leg', function () {
    $city = returnTestCity();
    // The seller sits in the very city the delivery failed in, so there is
    // nothing for a manifest to move.
    $seller = returnTestUser(Role::SELLER, $city);
    $driver = returnTestUser(Role::DRIVER, $city);
    $hub = returnTestUser(Role::DISPATCHER);
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    $transitions = app(ReturnTransitionService::class);
    $transitions->receiveAtHub($return->fresh(), $hub);
    $transitions->transition($return->fresh(), ReturnStatus::ARRIVED_VENDOR_HUB, $hub);

    expect($return->fresh()->status)->toBe(ReturnStatus::ARRIVED_VENDOR_HUB);
});

test('a return sitting in another city still has to ride a transfer', function () {
    $deliveryCity = returnTestCity();
    $sellerCity = City::query()->create([
        'name' => 'Seller City',
        'code' => 'SLC',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $seller = returnTestUser(Role::SELLER, $sellerCity);
    $driver = returnTestUser(Role::DRIVER, $deliveryCity);
    $hub = returnTestUser(Role::DISPATCHER);
    $order = returnTestOrder($seller, $deliveryCity, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    $transitions = app(ReturnTransitionService::class);
    $transitions->receiveAtHub($return->fresh(), $hub);
    $transitions->transition($return->fresh(), ReturnStatus::ARRIVED_VENDOR_HUB, $hub);
})->throws(ValidationException::class);

test('the hand-back leg cannot start without a driver', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER, $city);
    $driver = returnTestUser(Role::DRIVER, $city);
    $admin = returnTestUser(Role::ADMIN);

    $return = returnAtVendorHub($seller, $driver, $admin, $city);

    app(ReturnTransitionService::class)
        ->transition($return, ReturnStatus::IN_DELIVERY_TO_VENDOR, $admin);
})->throws(ValidationException::class);

test('only the assigned driver closes the return at the sellers door', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER, $city);
    $driver = returnTestUser(Role::DRIVER, $city);
    $otherDriver = returnTestUser(Role::DRIVER, $city);
    $admin = returnTestUser(Role::ADMIN);

    $return = returnAtVendorHub($seller, $driver, $admin, $city);
    app(ReturnTransitionService::class)->handBack($return, $admin, $driver);

    expect($return->fresh()->assigned_to)->toBe($driver->id);

    app(ReturnTransitionService::class)
        ->transition($return->fresh(), ReturnStatus::DELIVERED_TO_VENDOR, $otherDriver);
})->throws(AuthorizationException::class);

test('a driver from another city cannot be handed the parcel', function () {
    $city = returnTestCity();
    $elsewhere = City::query()->create([
        'name' => 'Elsewhere',
        'code' => 'ELS',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $seller = returnTestUser(Role::SELLER, $city);
    $driver = returnTestUser(Role::DRIVER, $city);
    $stranger = returnTestUser(Role::DRIVER, $elsewhere);
    $admin = returnTestUser(Role::ADMIN);

    $return = returnAtVendorHub($seller, $driver, $admin, $city);

    app(ReturnService::class)->assignDriver($return, $stranger, $admin);
})->throws(ValidationException::class);

test('the driver dropdown only offers drivers working the return city', function () {
    $city = returnTestCity();
    $elsewhere = City::query()->create([
        'name' => 'Far Away',
        'code' => 'FAR',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $driver = returnTestUser(Role::DRIVER, $city);
    returnTestUser(Role::DRIVER, $elsewhere);
    // Holds no return grant at all, so he can never close one.
    returnTestUser(Role::SELLER, $city);

    $options = app(ReturnService::class)->driverOptions($city->id);

    expect($options->pluck('id')->all())->toBe([$driver->id]);
});

test('seller can request return for delivered order', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    $return = app(ReturnService::class)->create(
        $order,
        $seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );

    expect($return->initiated_by_role)->toBe(ReturnInitiatedByRole::SELLER)
        ->and($order->fresh()->status)->toBe(OrderStatus::RETURN_REQUESTED);
});

test('customer data updates stay on return entity only', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);
    $order->update(['customer_first_name' => 'Original']);

    $return = app(ReturnService::class)->create(
        $order,
        $seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );

    app(ReturnService::class)->updateCustomerData($return, $seller, [
        'updated_customer_name' => 'Updated Name',
    ]);

    $return->refresh();

    expect($return->updated_customer_name)->toBe('Updated Name')
        ->and($order->fresh()->customer_first_name)->toBe('Original');
});

test('returns index is accessible with permission', function () {
    $admin = returnTestUser(Role::ADMIN);

    $this->actingAs($admin)
        ->get(route('returns.index'))
        ->assertOk();
});

test('seller can access returns index and create return request', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    $this->actingAs($seller)
        ->get(route('returns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('returns/index')
            ->where('can.create_request', true)
            ->where('can.manage', false)
        );

    $this->actingAs($seller)
        ->post(route('returns.store'), [
            'order_id' => $order->id,
            'reason' => ReturnReason::SELLER_REQUESTED->value,
        ])
        ->assertRedirect();
});

test('admin opens a return on behalf of a seller, stamped as admin-initiated', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $admin = returnTestUser(Role::ADMIN);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    // `create_request` stays a seller-side flag: staff go through the admin
    // path, which draws from every seller's pool rather than his own.
    $this->actingAs($admin)
        ->get(route('returns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.create_request', false));

    $this->actingAs($admin)
        ->post(route('returns.store'), [
            'order_id' => $order->id,
            'reason' => ReturnReason::ADMIN_DECISION->value,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('returns.eligible-orders'))
        ->assertOk();

    expect($order->fresh()->orderReturn->initiated_by_role)->toBe(ReturnInitiatedByRole::ADMIN);
});

test('a user with no return grant at all cannot open one', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $outsider = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    // A seller may only return his own parcels, and holds nothing that would
    // let him fall through to the admin path.
    $this->actingAs($outsider)
        ->post(route('returns.store'), [
            'order_id' => $order->id,
            'reason' => ReturnReason::SELLER_REQUESTED->value,
        ])
        ->assertForbidden();
});

test('the bulk screen sends a shelf of parcels out with one driver', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER, $city);
    $driver = returnTestUser(Role::DRIVER, $city);
    $hub = returnTestUser(Role::DISPATCHER, $city);

    $first = returnAtVendorHub($seller, $driver, $hub, $city);
    $second = returnAtVendorHub($seller, $driver, $hub, $city);

    $this->actingAs($hub)
        ->get(route('returns.hand-back'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('returns/hand-back')
            ->has('pending', 2)
            ->has('drivers', 1)
        );

    $this->actingAs($hub)
        ->post(route('returns.hand-back.dispatch'), [
            'items' => [
                ['reference' => $first->reference, 'driver_id' => $driver->id],
                ['reference' => $second->reference, 'driver_id' => $driver->id],
            ],
        ])
        ->assertRedirect();

    expect($first->fresh()->status)->toBe(ReturnStatus::IN_DELIVERY_TO_VENDOR)
        ->and($first->fresh()->assigned_to)->toBe($driver->id)
        ->and($second->fresh()->status)->toBe(ReturnStatus::IN_DELIVERY_TO_VENDOR);
});

test('a parcel that already left does not sink the rest of the batch', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER, $city);
    $driver = returnTestUser(Role::DRIVER, $city);
    $hub = returnTestUser(Role::DISPATCHER, $city);

    $gone = returnAtVendorHub($seller, $driver, $hub, $city);
    $waiting = returnAtVendorHub($seller, $driver, $hub, $city);

    // Somebody else dispatched this one while the shelf was being scanned.
    app(ReturnTransitionService::class)->handBack($gone, $hub, $driver);

    $result = app(ReturnHandBackService::class)->dispatchBatch($hub, [
        ['reference' => $gone->reference, 'driver_id' => $driver->id],
        ['reference' => $waiting->reference, 'driver_id' => $driver->id],
    ]);

    expect($result['dispatched'])->toBe(1)
        ->and($result['failures'])->toHaveCount(1)
        ->and($waiting->fresh()->status)->toBe(ReturnStatus::IN_DELIVERY_TO_VENDOR);
});

test('a seller cannot reach the bulk restitution screen', function () {
    $seller = returnTestUser(Role::SELLER);

    $this->actingAs($seller)
        ->get(route('returns.hand-back'))
        ->assertForbidden();
});

test('one order cannot have two non-cancelled returns', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    app(ReturnService::class)->create(
        $order,
        $seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );

    app(ReturnService::class)->create(
        $order->fresh(),
        $seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );
})->throws(ValidationException::class);
