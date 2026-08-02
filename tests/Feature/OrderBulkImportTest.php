<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
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

function importUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function importCity(string $name = 'Casablanca', string $code = 'CASA'): City
{
    return City::query()->create([
        'name' => $name,
        'code' => $code,
        'region' => 'Casablanca-Settat',
        'is_active' => true,
    ]);
}

function importSector(City $city, string $name = 'Maarif'): Sector
{
    return Sector::query()->create([
        'city_id' => $city->id,
        'name' => $name,
        'delivery_price' => 30.00,
        'is_active' => true,
    ]);
}

/**
 * @return array<string, mixed>
 */
function importRow(City $city, Sector $sector, array $overrides = []): array
{
    return array_merge([
        'customer_first_name' => 'Yasmine',
        'customer_last_name' => 'El Amrani',
        'customer_phone' => '0612345678',
        'customer_address' => '12 rue des Fleurs',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 450,
        'order_value' => 450,
        'notes' => null,
        'is_fragile' => false,
        'can_be_opened' => false,
        'option_exchange' => false,
    ], $overrides);
}

test('the import wizard is reachable by a seller', function () {
    $this->actingAs(importUser(Role::SELLER))
        ->get(route('orders.import'))
        ->assertOk();
});

test('a batch of rows creates one order per row', function () {
    $seller = importUser(Role::SELLER);
    $city = importCity();
    $sector = importSector($city);

    $response = $this->actingAs($seller)->post(route('orders.import.store'), [
        'orders' => [
            importRow($city, $sector),
            importRow($city, $sector, ['customer_first_name' => 'Omar', 'customer_phone' => '0623456789']),
        ],
    ]);

    $response->assertRedirect(route('orders.index'));
    expect(Order::query()->count())->toBe(2);

    $order = Order::query()->firstOrFail();
    expect($order->seller_id)->toBe($seller->id)
        ->and($order->status)->toBe(OrderStatus::CREATED)
        ->and($order->tracking_number)->not->toBeEmpty()
        // Pricing is owned by the sector, never by the uploaded file.
        ->and((float) $order->delivery_price)->toBe(30.0)
        ->and((float) $order->total_amount)->toBe(480.0);
});

test('a card payment row stores a value and collects nothing', function () {
    $city = importCity();
    $sector = importSector($city);

    $this->actingAs(importUser(Role::SELLER))->post(route('orders.import.store'), [
        'orders' => [
            importRow($city, $sector, [
                'payment_method' => PaymentMethod::CARD_PAYMENT->value,
                'order_amount' => 450,
                'order_value' => 450,
            ]),
        ],
    ]);

    $order = Order::query()->firstOrFail();
    expect($order->order_amount)->toBeNull()
        ->and((float) $order->order_value)->toBe(450.0);
});

test('a sector belonging to another city is rejected on its own row', function () {
    $city = importCity();
    $sector = importSector($city);
    $otherCity = importCity('Rabat', 'RBA');

    $response = $this->actingAs(importUser(Role::SELLER))->post(route('orders.import.store'), [
        'orders' => [
            importRow($city, $sector),
            importRow($city, $sector, ['city_id' => $otherCity->id]),
        ],
    ]);

    $response->assertSessionHasErrors('orders.1.sector_id');
    $response->assertSessionDoesntHaveErrors('orders.0.sector_id');
});

test('no order is written when a single row fails validation', function () {
    $city = importCity();
    $sector = importSector($city);

    $this->actingAs(importUser(Role::SELLER))->post(route('orders.import.store'), [
        'orders' => [
            importRow($city, $sector),
            importRow($city, $sector, ['customer_phone' => '']),
        ],
    ]);

    expect(Order::query()->count())->toBe(0);
});

test('a cash row without an amount is rejected', function () {
    $city = importCity();
    $sector = importSector($city);

    $response = $this->actingAs(importUser(Role::SELLER))->post(route('orders.import.store'), [
        'orders' => [importRow($city, $sector, ['order_amount' => null, 'order_value' => null])],
    ]);

    $response->assertSessionHasErrors('orders.0.order_amount');
});

test('an unknown payment method is reported instead of silently becoming cash', function () {
    $city = importCity();
    $sector = importSector($city);

    $response = $this->actingAs(importUser(Role::SELLER))->post(route('orders.import.store'), [
        'orders' => [importRow($city, $sector, ['payment_method' => 'BITCOIN'])],
    ]);

    $response->assertSessionHasErrors('orders.0.payment_method');
});

test('a user without the create permission cannot import', function () {
    $city = importCity();
    $sector = importSector($city);

    $this->actingAs(importUser(Role::DRIVER))
        ->post(route('orders.import.store'), ['orders' => [importRow($city, $sector)]])
        ->assertForbidden();

    expect(Order::query()->count())->toBe(0);
});
