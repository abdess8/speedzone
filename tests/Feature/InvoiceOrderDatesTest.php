<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Http\Resources\InvoiceResource;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\BillingService;
use App\Services\InvoiceGeneratorService;
use App\Services\ReturnService;
use App\Services\ReturnTransitionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->city = City::query()->create([
        'name' => 'Billing City',
        'code' => 'BIL',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $this->sector = Sector::query()->create([
        'city_id' => $this->city->id,
        'name' => 'Billing Sector',
        'delivery_price' => 30,
        'return_price' => 12,
        'is_active' => true,
    ]);

    $this->seller = invoiceDatesUser(Role::SELLER);
    $this->admin = invoiceDatesUser(Role::ADMIN);
});

function invoiceDatesUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id, 'city_id' => null]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function invoiceDatesOrder(User $seller, City $city, Sector $sector, OrderStatus $status): Order
{
    return Order::query()->create([
        'tracking_number' => 'BIL-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'customer_first_name' => 'Karim',
        'customer_last_name' => 'Client',
        'customer_phone' => '0611111111',
        'customer_address' => '12 Billing Street',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 200,
        'delivery_price' => 30,
        'status' => $status->value,
    ]);
}

test('an order stamps the day it comes back to the seller', function () {
    $order = invoiceDatesOrder($this->seller, $this->city, $this->sector, OrderStatus::DELIVERED);

    $return = app(ReturnService::class)->create(
        $order,
        $this->seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );

    expect($order->fresh()->returned_at)->toBeNull();

    $transitions = app(ReturnTransitionService::class);
    $transitions->moveToDepot($return->fresh(), $this->admin);
    $transitions->transition($return->fresh(), ReturnStatus::RECEIVED_AT_DEPOT, $this->admin);
    $transitions->transition($return->fresh(), ReturnStatus::IN_TRANSIT_TO_SELLER, $this->admin);
    $transitions->transition($return->fresh(), ReturnStatus::DELIVERED_TO_SELLER, $this->admin);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::RETURNED)
        ->and($order->returned_at)->not->toBeNull()
        ->and($order->completedAt()->toDateString())->toBe(now()->toDateString());
});

test('cancelling a return clears the return date', function () {
    $order = invoiceDatesOrder($this->seller, $this->city, $this->sector, OrderStatus::DELIVERED);

    $return = app(ReturnService::class)->create(
        $order,
        $this->seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );

    app(ReturnService::class)->applyStatus($return->fresh(), ReturnStatus::CANCELLED, $this->admin);

    expect($order->fresh()->returned_at)->toBeNull();
});

test('invoice lines snapshot the delivery and return dates', function () {
    $delivered = invoiceDatesOrder($this->seller, $this->city, $this->sector, OrderStatus::DELIVERED);
    $delivered->forceFill(['delivered_at' => now()->subDays(3)])->save();

    $returned = invoiceDatesOrder($this->seller, $this->city, $this->sector, OrderStatus::RETURNED);
    $returned->forceFill(['returned_at' => now()->subDay(), 'is_returned' => true])->save();

    $invoice = app(InvoiceGeneratorService::class)->generate($this->seller, createdBy: $this->admin);

    expect($invoice)->not->toBeNull();

    $lines = $invoice->invoiceOrders()->get()->keyBy('order_id');

    expect($lines[$delivered->id]->order_completed_at->toDateString())
        ->toBe(now()->subDays(3)->toDateString())
        ->and($lines[$returned->id]->order_completed_at->toDateString())
        ->toBe(now()->subDay()->toDateString());
});

test('the invoice payload exposes the completion date of every line', function () {
    $delivered = invoiceDatesOrder($this->seller, $this->city, $this->sector, OrderStatus::DELIVERED);
    $delivered->forceFill(['delivered_at' => now()->subDays(2)])->save();

    $invoice = app(InvoiceGeneratorService::class)->generate($this->seller, createdBy: $this->admin);
    $invoice->load(['invoiceOrders.order.city', 'invoiceOrders.order.sector']);

    $payload = InvoiceResource::make($invoice)->resolve(request());

    expect($payload['lines'][0]['completed_at'])->toStartWith(now()->subDays(2)->toDateString());
});

test('a pending billing preview carries the completion date', function () {
    $delivered = invoiceDatesOrder($this->seller, $this->city, $this->sector, OrderStatus::DELIVERED);
    $delivered->forceFill(['delivered_at' => now()->subDays(4)])->save();

    $preview = app(BillingService::class)->preview($this->seller);

    expect($preview['lines'][0]['completed_at'])->toStartWith(now()->subDays(4)->toDateString());
});
