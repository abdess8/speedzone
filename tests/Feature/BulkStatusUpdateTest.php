<?php

use App\Enums\BulkStatusEntityType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\BulkStatusChangeLog;
use App\Models\City;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\StatusTransitionAccessService;
use App\Support\StatusTransitionPermissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function bulkUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function bulkCity(): City
{
    return City::query()->create([
        'name' => 'Bulk City',
        'code' => 'BLK',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function bulkOrder(User $seller, City $city, OrderStatus $status, ?User $driver = null): Order
{
    return Order::query()->create([
        'tracking_number' => 'BLK-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'driver_id' => $driver?->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '1 Bulk Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => $status->value,
    ])->fresh();
}

/**
 * Strip a role back to exactly the pairs named, so a test can describe the
 * "User 1" of the specification instead of inheriting the seeded matrix.
 *
 * @param  array<int, string>  $pairs
 */
function grantOnlyTransitions(User $user, array $pairs): void
{
    $role = $user->roles->first();

    $keep = Permission::query()
        ->whereIn('name', $pairs)
        ->orWhere('type', '!=', StatusTransitionPermissions::TYPE)
        ->pluck('id');

    $role->permissions()->sync(
        $role->permissions->pluck('id')->intersect($keep)->all()
    );

    $user->refresh()->load('roles.permissions');
}

it('offers only the target statuses the user holds a transition into', function () {
    $driver = bulkUser(Role::DRIVER);

    grantOnlyTransitions($driver, [
        'orders.status_transition.out_for_delivery.delivered',
        'orders.status_transition.in_delivery_city.out_for_delivery',
    ]);

    $targets = app(StatusTransitionAccessService::class)
        ->targets($driver->fresh(['roles.permissions']), BulkStatusEntityType::ORDER);

    expect($targets)->toEqualCanonicalizing([
        OrderStatus::DELIVERED->value,
        OrderStatus::OUT_FOR_DELIVERY->value,
    ]);
});

it('narrows the source statuses to those that lead to the chosen target', function () {
    $driver = bulkUser(Role::DRIVER);

    grantOnlyTransitions($driver, [
        'orders.status_transition.out_for_delivery.delivered',
        'orders.status_transition.in_delivery_city.out_for_delivery',
    ]);

    $access = app(StatusTransitionAccessService::class);
    $driver = $driver->fresh(['roles.permissions']);

    expect($access->sources($driver, BulkStatusEntityType::ORDER, OrderStatus::DELIVERED->value))
        ->toEqual([OrderStatus::OUT_FOR_DELIVERY->value]);

    expect($access->sources($driver, BulkStatusEntityType::ORDER, OrderStatus::OUT_FOR_DELIVERY->value))
        ->toEqual([OrderStatus::IN_DELIVERY_CITY->value]);
});

it('lists only eligible orders the user can already reach', function () {
    $seller = bulkUser(Role::SELLER);
    $driver = bulkUser(Role::DRIVER);
    $city = bulkCity();

    $mine = bulkOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);
    // Right status, someone else's round.
    $someoneElses = bulkOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, bulkUser(Role::DRIVER));
    // My round, but no transition leads from CREATED to DELIVERED.
    $wrongStatus = bulkOrder($seller, $city, OrderStatus::CREATED, $driver);

    $response = $this->actingAs($driver)
        ->getJson(route('bulk-status.items', [
            'entity_type' => BulkStatusEntityType::ORDER->value,
            'to_status' => OrderStatus::DELIVERED->value,
        ]))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id)
        ->not->toContain($someoneElses->id)
        ->not->toContain($wrongStatus->id);
});

it('applies the batch and reports each item', function () {
    $seller = bulkUser(Role::SELLER);
    $driver = bulkUser(Role::DRIVER);
    $city = bulkCity();

    $ok = bulkOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);
    $stale = bulkOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    $this->actingAs($driver)
        ->post(route('bulk-status.store'), [
            'entity_type' => BulkStatusEntityType::ORDER->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'items' => [
                ['id' => $ok->id, 'from_status' => OrderStatus::OUT_FOR_DELIVERY->value],
                // Selected under a status it is not actually in any more.
                ['id' => $stale->id, 'from_status' => OrderStatus::IN_DELIVERY_CITY->value],
            ],
        ])
        ->assertRedirect();

    expect($ok->fresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and($stale->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);

    $log = BulkStatusChangeLog::query()->get();

    expect($log)->toHaveCount(2)
        ->and($log->where('successful', true)->count())->toBe(1)
        ->and($log->firstWhere('entity_id', $stale->id)->failure_reason->value)->toBe('STATUS_CHANGED');
});

it('refuses a transition the user is not granted even when the graph allows it', function () {
    $seller = bulkUser(Role::SELLER);
    $driver = bulkUser(Role::DRIVER);
    $city = bulkCity();

    grantOnlyTransitions($driver, ['orders.status_transition.in_delivery_city.out_for_delivery']);

    $order = bulkOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    $this->actingAs($driver->fresh(['roles.permissions']))
        ->post(route('bulk-status.store'), [
            'entity_type' => BulkStatusEntityType::ORDER->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'items' => [['id' => $order->id]],
        ])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('never moves an order the user cannot reach, even when its id is submitted', function () {
    $seller = bulkUser(Role::SELLER);
    $driver = bulkUser(Role::DRIVER);
    $city = bulkCity();

    $foreign = bulkOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, bulkUser(Role::DRIVER));

    $this->actingAs($driver)
        ->post(route('bulk-status.store'), [
            'entity_type' => BulkStatusEntityType::ORDER->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'items' => [['id' => $foreign->id]],
        ])
        ->assertRedirect();

    expect($foreign->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY)
        ->and(BulkStatusChangeLog::query()->first()->failure_reason->value)->toBe('NOT_FOUND');
});

it('rejects a scanned parcel whose status does not lead to the target', function () {
    $seller = bulkUser(Role::SELLER);
    $driver = bulkUser(Role::DRIVER);
    $city = bulkCity();

    $order = bulkOrder($seller, $city, OrderStatus::IN_DELIVERY_CITY, $driver);

    $this->actingAs($driver)
        ->postJson(route('bulk-status.scan'), [
            'entity_type' => BulkStatusEntityType::ORDER->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'scan' => $order->tracking_number,
        ])
        ->assertOk()
        ->assertJson(['valid' => false]);
});

it('accepts a scanned parcel that qualifies, by url or bare reference', function () {
    $seller = bulkUser(Role::SELLER);
    $driver = bulkUser(Role::DRIVER);
    $city = bulkCity();

    $order = bulkOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    $this->actingAs($driver)
        ->postJson(route('bulk-status.scan'), [
            'entity_type' => BulkStatusEntityType::ORDER->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'scan' => $order->trackingUrl(),
        ])
        ->assertOk()
        ->assertJson([
            'valid' => true,
            'item' => ['id' => $order->id, 'reference' => $order->tracking_number],
        ]);
});

it('keeps the transition permission screen to administrators', function () {
    $this->actingAs(bulkUser(Role::DISPATCHER))
        ->get(route('status-transition-permissions.index'))
        ->assertForbidden();

    $this->actingAs(bulkUser(Role::ADMIN))
        ->get(route('status-transition-permissions.index'))
        ->assertOk();
});

it('lets an administrator grant and revoke one transition for one role', function () {
    $admin = bulkUser(Role::ADMIN);
    $role = Role::query()->where('name', Role::DRIVER)->firstOrFail();
    $permission = 'orders.status_transition.created.canceled';

    $this->actingAs($admin)
        ->put(route('status-transition-permissions.update'), [
            'permission' => $permission,
            'role_id' => $role->id,
            'granted' => true,
        ])
        ->assertRedirect();

    expect($role->fresh()->permissions->pluck('name'))->toContain($permission);

    $this->actingAs($admin)
        ->put(route('status-transition-permissions.update'), [
            'permission' => $permission,
            'role_id' => $role->id,
            'granted' => false,
        ])
        ->assertRedirect();

    expect($role->fresh()->permissions->pluck('name'))->not->toContain($permission);
});

it('keeps the bulk screen shut for a user with no transition at all', function () {
    $seller = bulkUser(Role::SELLER);

    $this->actingAs($seller)
        ->get(route('bulk-status.index'))
        ->assertForbidden();
});
