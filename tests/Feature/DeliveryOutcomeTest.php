<?php

use App\Enums\DeliveryOutcome;
use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function outcomeUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function outcomeCity(): City
{
    return City::query()->create([
        'name' => 'Outcome City',
        'code' => 'OUC',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function outcomeOrder(User $seller, City $city, ?User $driver = null, ?OrderStatus $status = null): Order
{
    return Order::query()->create([
        'tracking_number' => 'OUT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'driver_id' => $driver?->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '1 Outcome Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => ($status ?? OrderStatus::OUT_FOR_DELIVERY)->value,
    ])->fresh();
}

it('completes the order when the driver reports a successful hand-over', function () {
    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), $driver);

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::DELIVERED->value,
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::DELIVERED)
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->failed_attempts_count)->toBe(0);
});

it('keeps a retryable failure on the round and counts the attempt', function () {
    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), $driver);

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::FAILED->value,
            'failure_reason' => OrderFailureReason::CUSTOMER_ABSENT->value,
            'note' => 'Nobody home at 14:00.',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::OUT_FOR_DELIVERY)
        ->and($order->failed_attempts_count)->toBe(1)
        ->and($order->failure_reason)->toBe(OrderFailureReason::CUSTOMER_ABSENT)
        ->and($order->failure_note)->toBe('Nobody home at 14:00.')
        ->and($order->failed_at)->not->toBeNull();

    $entry = $order->statusHistories()->latest('id')->first();

    expect($entry->status)->toBe(OrderStatus::OUT_FOR_DELIVERY)
        ->and($entry->comment)->toContain(OrderFailureReason::CUSTOMER_ABSENT->label())
        ->and($entry->comment)->toContain('Nobody home at 14:00.');
});

it('lets the driver keep trying, counting every attempt', function () {
    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), $driver);

    foreach ([OrderFailureReason::CUSTOMER_ABSENT, OrderFailureReason::CUSTOMER_UNREACHABLE] as $reason) {
        $this->actingAs($driver)
            ->post(route('orders.delivery-outcome', $order), [
                'outcome' => DeliveryOutcome::FAILED->value,
                'failure_reason' => $reason->value,
            ])
            ->assertRedirect();
    }

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::OUT_FOR_DELIVERY)
        ->and($order->failed_attempts_count)->toBe(2);

    // A third round can still end with the parcel in the customer's hands.
    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::DELIVERED->value,
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::DELIVERED);
});

it('sends a refused delivery straight into the return pipeline', function () {
    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), $driver);

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::FAILED->value,
            'failure_reason' => OrderFailureReason::CUSTOMER_REFUSED->value,
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::READY_TO_RETURN)
        ->and($order->failure_reason)->toBe(OrderFailureReason::CUSTOMER_REFUSED)
        // The parcel never went out again, so nothing is counted as an attempt.
        ->and($order->failed_attempts_count)->toBe(0);
});

it('sends a cancelled delivery straight into the return pipeline', function () {
    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), $driver);

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::FAILED->value,
            'failure_reason' => OrderFailureReason::CUSTOMER_CANCELED->value,
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::READY_TO_RETURN);
});

it('refuses a non-delivery that carries no reason', function () {
    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), $driver);

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::FAILED->value,
        ])
        ->assertSessionHasErrors('failure_reason');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::OUT_FOR_DELIVERY)
        ->and($order->failed_attempts_count)->toBe(0);
});

it('stores the attachment on the history entry it belongs to', function () {
    Storage::fake('public');

    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), $driver);

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::FAILED->value,
            'failure_reason' => OrderFailureReason::WRONG_ADDRESS->value,
            'attachment' => UploadedFile::fake()->image('closed-shop.jpg'),
        ])
        ->assertRedirect();

    $entry = $order->fresh()->statusHistories()->latest('id')->first();

    expect($entry->attachment_name)->toBe('closed-shop.jpg')
        ->and($entry->attachment_path)->not->toBeNull();

    Storage::disk('public')->assertExists($entry->attachment_path);

    // A second attempt must not overwrite the proof of the first.
    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::FAILED->value,
            'failure_reason' => OrderFailureReason::CUSTOMER_ABSENT->value,
        ])
        ->assertRedirect();

    expect($entry->fresh()->attachment_name)->toBe('closed-shop.jpg');
});

it('rejects an attachment that is not an image or a pdf', function () {
    Storage::fake('public');

    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), $driver);

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::FAILED->value,
            'failure_reason' => OrderFailureReason::OTHER->value,
            'attachment' => UploadedFile::fake()->create('payload.exe', 10),
        ])
        ->assertSessionHasErrors('attachment');
});

it('refuses an outcome for an order that is not out for delivery', function () {
    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(
        outcomeUser(Role::SELLER),
        outcomeCity(),
        $driver,
        OrderStatus::IN_DELIVERY_CITY
    );

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::DELIVERED->value,
        ])
        ->assertSessionHasErrors('outcome');

    expect($order->fresh()->status)->toBe(OrderStatus::IN_DELIVERY_CITY);
});

it('does not let a driver close a delivery assigned to somebody else', function () {
    $driver = outcomeUser(Role::DRIVER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity(), outcomeUser(Role::DRIVER));

    $this->actingAs($driver)
        ->post(route('orders.delivery-outcome', $order), [
            'outcome' => DeliveryOutcome::DELIVERED->value,
        ])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('hands the delivery leg to the outcome flow on the detail screen', function () {
    $dispatcher = outcomeUser(Role::DISPATCHER);
    $order = outcomeOrder(outcomeUser(Role::SELLER), outcomeCity());

    $props = $this->actingAs($dispatcher)
        ->get(route('orders.show', $order))
        ->viewData('page')['props'];

    expect($props['deliveryOutcome']['reportable'])->toBeTrue();

    // Neither ending is offered twice: the dropdown keeps only the statuses the
    // outcome flow does not own.
    $offered = collect($props['allowedTransitions'])->pluck('value');

    expect($offered)->not->toContain(OrderStatus::DELIVERED->value)
        ->and($offered)->not->toContain(OrderStatus::READY_TO_RETURN->value);
});
