<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderChangeHistory;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\BillingService;
use App\Services\LabelIconService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $role = Role::query()->where('name', Role::SELLER)->firstOrFail();
    $this->seller = User::factory()->create(['role_id' => $role->id]);
    $this->seller->roles()->sync([$role->id]);
    $this->seller = $this->seller->fresh(['roles.permissions']);

    $this->city = City::query()->create([
        'name' => 'Included City',
        'code' => 'INC',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $this->sector = Sector::query()->create([
        'city_id' => $this->city->id,
        'name' => 'Included Sector',
        'delivery_price' => 25.00,
        'return_price' => 15.00,
        'is_active' => true,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function includedOrderPayload(City $city, Sector $sector, array $overrides = []): array
{
    return array_merge([
        'customer_first_name' => 'John',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '123 Test Street',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 100,
        'delivery_price' => 25,
    ], $overrides);
}

test('an order sold at a delivered price asks the customer for the goods only', function () {
    Sanctum::actingAs($this->seller);

    $this->postJson(
        route('api.orders.store'),
        includedOrderPayload($this->city, $this->sector, ['delivery_included' => true])
    )->assertCreated();

    $order = Order::query()->latest('id')->firstOrFail();

    expect((float) $order->total_amount)->toBe(100.0)
        ->and((float) $order->order_amount)->toBe(100.0)
        // The fee is not waived, only excluded from what is collected at the door.
        ->and((float) $order->delivery_price)->toBe(25.0)
        ->and($order->delivery_included)->toBeTrue();
});

test('an order without the flag still charges the delivery on top', function () {
    Sanctum::actingAs($this->seller);

    $this->postJson(
        route('api.orders.store'),
        includedOrderPayload($this->city, $this->sector)
    )->assertCreated();

    $order = Order::query()->latest('id')->firstOrFail();

    expect((float) $order->total_amount)->toBe(125.0)
        ->and($order->delivery_included)->toBeFalse();
});

test('the seller is still billed the delivery fee when he included it in his price', function () {
    $order = Order::query()->create([
        ...includedOrderPayload($this->city, $this->sector, ['delivery_included' => true]),
        'tracking_number' => 'INC-2026-000001',
        'seller_id' => $this->seller->id,
        'status' => OrderStatus::DELIVERED->value,
        'delivered_at' => now(),
    ]);

    $line = app(BillingService::class)->computeLine($order->fresh(['sector']));

    // Absorbing the shipping is a commercial choice between the seller and his
    // customer; it does not change what he owes us for carrying the parcel.
    expect($line['delivery_fee'])->toBe(25.0)
        ->and($line['final_amount'])->toBe(75.0);
});

test('the ticket never puts the delivery fee in front of the driver', function () {
    $order = Order::query()->create([
        ...includedOrderPayload($this->city, $this->sector, ['delivery_included' => true]),
        'tracking_number' => 'INC-2026-000002',
        'seller_id' => $this->seller->id,
        'status' => OrderStatus::CREATED->value,
    ]);

    $html = view('orders._label_body', [
        'order' => $order->fresh(['city', 'sector', 'seller']),
        'icons' => app(LabelIconService::class)->labelIcons(),
        'barcode' => '',
        'qrCode' => '',
        'logo' => null,
        'companyName' => 'SpeedZone Express',
    ])->render();

    // Both money boxes on the label have to agree, and neither may leak the fee:
    // the driver reads this to know what to bring back.
    expect(substr_count($html, '100.00 MAD'))->toBe(2)
        ->and($html)->not->toContain('25.00');
});

test('including the delivery later recomputes the total and is written to the history', function () {
    $order = Order::query()->create([
        ...includedOrderPayload($this->city, $this->sector),
        'tracking_number' => 'INC-2026-000003',
        'seller_id' => $this->seller->id,
        'status' => OrderStatus::CREATED->value,
    ]);

    expect((float) $order->total_amount)->toBe(125.0);

    Sanctum::actingAs($this->seller);

    $this->putJson(route('api.orders.update', $order), ['delivery_included' => true])
        ->assertOk();

    expect((float) $order->fresh()->total_amount)->toBe(100.0);

    $change = OrderChangeHistory::query()
        ->where('order_id', $order->id)
        ->where('field_name', 'delivery_included')
        ->firstOrFail();

    expect($change->old_value)->toBe('No')
        ->and($change->new_value)->toBe('Yes');
});
