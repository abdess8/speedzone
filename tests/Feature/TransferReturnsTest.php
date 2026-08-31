<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\TransferContentType;
use App\Enums\TransferStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Role;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\ReturnTransitionService;
use App\Services\TransferService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function transferTestCity(string $name, string $code): City
{
    return City::query()->create([
        'name' => $name,
        'code' => $code,
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function transferTestUser(string $roleName, ?int $cityId = null): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id, 'city_id' => $cityId]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

/**
 * A return sitting at the delivery city's hub, waiting for a ride back to the
 * seller's city — the only state from which it may join a manifest.
 */
function returnWaitingAtHub(User $seller, City $deliveryCity): OrderReturn
{
    $driver = transferTestUser(Role::DRIVER);
    $admin = transferTestUser(Role::ADMIN);

    $order = Order::query()->create([
        'tracking_number' => 'TRR-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Customer',
        'customer_phone' => '0611111111',
        'customer_address' => '456 Return Street',
        'city_id' => $deliveryCity->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => OrderStatus::OUT_FOR_DELIVERY->value,
    ]);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    app(ReturnTransitionService::class)->receiveAtHub($return->fresh(), $admin);

    $return->refresh();
    $return->update(['current_location_city_id' => $deliveryCity->id]);

    return $return->fresh();
}

test('a returns-only manifest carries the returns and leaves the order pool alone', function () {
    $deliveryCity = transferTestCity('Delivery City', 'DLV');
    $sellerCity = transferTestCity('Seller City', 'SLR');
    $seller = transferTestUser(Role::SELLER, $sellerCity->id);
    $admin = transferTestUser(Role::ADMIN);

    $return = returnWaitingAtHub($seller, $deliveryCity);

    $transfer = app(TransferService::class)->create(
        $admin,
        $deliveryCity->id,
        $sellerCity->id,
        orderIds: [],
        contentType: TransferContentType::RETURNS,
        returnIds: [$return->id],
    );

    expect($transfer->content_type)->toBe(TransferContentType::RETURNS)
        ->and($transfer->number_of_returns)->toBe(1)
        ->and($transfer->number_of_packages)->toBe(1)
        ->and($transfer->returns()->pluck('returns.id')->all())->toBe([$return->id])
        // Filling the manifest must not move the parcel: it is still on the
        // hub's shelf until the truck leaves.
        ->and($return->fresh()->status)->toBe(ReturnStatus::RECEIVED_AT_HUB);
});

test('the manifest walks its returns from the hub shelf to the seller city', function () {
    $deliveryCity = transferTestCity('Delivery City', 'DLV');
    $sellerCity = transferTestCity('Seller City', 'SLR');
    $seller = transferTestUser(Role::SELLER, $sellerCity->id);
    $admin = transferTestUser(Role::ADMIN);

    $return = returnWaitingAtHub($seller, $deliveryCity);
    $transfers = app(TransferService::class);

    $transfer = $transfers->create(
        $admin,
        $deliveryCity->id,
        $sellerCity->id,
        orderIds: [],
        contentType: TransferContentType::RETURNS,
        returnIds: [$return->id],
    );

    $transfers->applyStatus($transfer, TransferStatus::IN_TRANSIT, $admin);

    expect($return->fresh()->status)->toBe(ReturnStatus::IN_TRANSIT_TO_DEPOT);

    $transfers->applyStatus($transfer->fresh(), TransferStatus::RECEIVED, $admin);

    $return->refresh();

    expect($return->status)->toBe(ReturnStatus::ARRIVED_VENDOR_HUB)
        ->and($return->current_location_city_id)->toBe($sellerCity->id);
});

test('cancelling a manifest puts its returns back in the pool', function () {
    $deliveryCity = transferTestCity('Delivery City', 'DLV');
    $sellerCity = transferTestCity('Seller City', 'SLR');
    $seller = transferTestUser(Role::SELLER, $sellerCity->id);
    $admin = transferTestUser(Role::ADMIN);

    $return = returnWaitingAtHub($seller, $deliveryCity);
    $transfers = app(TransferService::class);

    $transfer = $transfers->create(
        $admin,
        $deliveryCity->id,
        $sellerCity->id,
        orderIds: [],
        contentType: TransferContentType::RETURNS,
        returnIds: [$return->id],
    );

    expect($transfers->getEligibleReturns($deliveryCity->id, $sellerCity->id))->toHaveCount(0);

    $transfers->applyStatus($transfer, TransferStatus::CANCELLED, $admin);

    expect($return->fresh()->status)->toBe(ReturnStatus::RECEIVED_AT_HUB)
        ->and($transfers->getEligibleReturns($deliveryCity->id, $sellerCity->id))->toHaveCount(1);
});

test('a mixed manifest reports the pools it actually drew from', function () {
    $deliveryCity = transferTestCity('Delivery City', 'DLV');
    $sellerCity = transferTestCity('Seller City', 'SLR');
    $seller = transferTestUser(Role::SELLER, $sellerCity->id);
    $admin = transferTestUser(Role::ADMIN);

    $return = returnWaitingAtHub($seller, $deliveryCity);

    // Started as mixed, filled with returns only: the manifest should not keep
    // advertising an order leg it never had.
    $transfer = app(TransferService::class)->create(
        $admin,
        $deliveryCity->id,
        $sellerCity->id,
        orderIds: [],
        contentType: TransferContentType::MIXED,
        returnIds: [$return->id],
    );

    expect($transfer->content_type)->toBe(TransferContentType::RETURNS);
});

test('a return already on a live manifest cannot join a second one', function () {
    $deliveryCity = transferTestCity('Delivery City', 'DLV');
    $sellerCity = transferTestCity('Seller City', 'SLR');
    $seller = transferTestUser(Role::SELLER, $sellerCity->id);
    $admin = transferTestUser(Role::ADMIN);

    $return = returnWaitingAtHub($seller, $deliveryCity);
    $transfers = app(TransferService::class);

    $transfers->create(
        $admin,
        $deliveryCity->id,
        $sellerCity->id,
        orderIds: [],
        contentType: TransferContentType::RETURNS,
        returnIds: [$return->id],
    );

    $transfers->create(
        $admin,
        $deliveryCity->id,
        $sellerCity->id,
        orderIds: [],
        contentType: TransferContentType::RETURNS,
        returnIds: [$return->id],
    );
})->throws(ValidationException::class);

test('a return whose seller sits elsewhere is refused by the manifest', function () {
    $deliveryCity = transferTestCity('Delivery City', 'DLV');
    $sellerCity = transferTestCity('Seller City', 'SLR');
    $otherCity = transferTestCity('Other City', 'OTH');
    $seller = transferTestUser(Role::SELLER, $sellerCity->id);
    $admin = transferTestUser(Role::ADMIN);

    $return = returnWaitingAtHub($seller, $deliveryCity);

    app(TransferService::class)->create(
        $admin,
        $deliveryCity->id,
        $otherCity->id,
        orderIds: [],
        contentType: TransferContentType::RETURNS,
        returnIds: [$return->id],
    );
})->throws(ValidationException::class);
