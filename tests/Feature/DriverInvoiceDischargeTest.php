<?php

use App\Enums\DriverTransactionStatus;
use App\Enums\DriverTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\DriverTransaction;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\DriverBillingService;
use App\Services\DriverInvoiceGeneratorService;
use App\Services\DriverPaymentService;
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
        'name' => 'Discharge City',
        'code' => 'DCH',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $this->sector = Sector::query()->create([
        'city_id' => $this->city->id,
        'name' => 'Discharge Sector',
        'delivery_price' => 30,
        'return_price' => 12,
        'delivery_driver_price' => 15,
        'is_active' => true,
    ]);

    $this->seller = dischargeUser(Role::SELLER);
    $this->driver = dischargeUser(Role::DRIVER);
    $this->admin = dischargeUser(Role::ADMIN);
});

function dischargeUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id, 'city_id' => null]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function dischargeOrder(User $seller, User $driver, City $city, Sector $sector, array $overrides = []): Order
{
    static $sequence = 0;
    $sequence++;

    return Order::query()->create(array_merge([
        'tracking_number' => 'DCH-2026-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'driver_id' => $driver->id,
        'customer_first_name' => 'Client',
        'customer_last_name' => 'Livré',
        'customer_phone' => '0611111111',
        'customer_address' => '12 Discharge Street',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 200,
        'delivery_price' => 30,
        'delivery_included' => false,
        'status' => OrderStatus::DELIVERED->value,
        'delivered_at' => now()->subDay(),
    ], $overrides))->fresh();
}

function dischargeDelivery(Order $order): DriverTransaction
{
    return app(DriverPaymentService::class)->recordDeliveryPayment($order);
}

test('a cash delivery remits the collected total minus the driver price', function () {
    $order = dischargeOrder($this->seller, $this->driver, $this->city, $this->sector);
    dischargeDelivery($order);

    // Customer paid 200 + 30 delivery; the driver keeps 15 and remits 215.
    expect((float) $order->total_amount)->toBe(230.0);

    $summary = app(DriverBillingService::class)->preview($this->driver)['summary'];

    expect($summary['deliveries_count'])->toBe(1)
        ->and($summary['collected_amount'])->toBe(230.0)
        ->and($summary['commission_total'])->toBe(15.0)
        ->and($summary['total_amount'])->toBe(215.0);
});

test('a delivered-price cash order remits the goods amount minus the driver price', function () {
    $order = dischargeOrder($this->seller, $this->driver, $this->city, $this->sector, [
        'delivery_included' => true,
    ]);
    dischargeDelivery($order);

    expect((float) $order->total_amount)->toBe(200.0);

    $summary = app(DriverBillingService::class)->preview($this->driver)['summary'];

    expect($summary['collected_amount'])->toBe(200.0)
        ->and($summary['commission_total'])->toBe(15.0)
        ->and($summary['total_amount'])->toBe(185.0);
});

test('a card delivery collects nothing so the driver is owed his commission', function () {
    $order = dischargeOrder($this->seller, $this->driver, $this->city, $this->sector, [
        'payment_method' => PaymentMethod::CARD_PAYMENT->value,
        'order_amount' => null,
    ]);
    dischargeDelivery($order);

    $summary = app(DriverBillingService::class)->preview($this->driver)['summary'];

    expect($summary['collected_amount'])->toBe(0.0)
        ->and($summary['commission_total'])->toBe(15.0)
        ->and($summary['total_amount'])->toBe(-15.0);
});

test('bonuses reduce the remittance and penalties increase it', function () {
    $order = dischargeOrder($this->seller, $this->driver, $this->city, $this->sector);
    dischargeDelivery($order);

    DriverTransaction::query()->create([
        'driver_id' => $this->driver->id,
        'amount' => 50,
        'driver_price_snapshot' => 0,
        'transaction_type' => DriverTransactionType::BONUS->value,
        'status' => DriverTransactionStatus::CONFIRMED->value,
    ]);

    DriverTransaction::query()->create([
        'driver_id' => $this->driver->id,
        'amount' => -20,
        'driver_price_snapshot' => 0,
        'transaction_type' => DriverTransactionType::PENALTY->value,
        'status' => DriverTransactionStatus::CONFIRMED->value,
    ]);

    $summary = app(DriverBillingService::class)->preview($this->driver)['summary'];

    expect($summary['collected_amount'])->toBe(230.0)
        ->and($summary['commission_total'])->toBe(15.0)
        ->and($summary['bonus_total'])->toBe(50.0)
        ->and($summary['penalty_total'])->toBe(20.0)
        ->and($summary['total_amount'])->toBe(185.0);
});

test('generating a driver invoice snapshots the discharge totals', function () {
    $cash = dischargeOrder($this->seller, $this->driver, $this->city, $this->sector);
    $prepaid = dischargeOrder($this->seller, $this->driver, $this->city, $this->sector, [
        'payment_method' => PaymentMethod::CARD_PAYMENT->value,
        'order_amount' => null,
        'delivery_included' => true,
    ]);
    dischargeDelivery($cash);
    dischargeDelivery($prepaid);

    $invoice = app(DriverInvoiceGeneratorService::class)->generate($this->driver, createdBy: $this->admin);

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->collected_amount)->toBe(230.0)
        ->and((float) $invoice->commission_total)->toBe(30.0)
        ->and((float) $invoice->total_amount)->toBe(200.0)
        ->and($invoice->deliveries_count)->toBe(2);

    $lines = $invoice->invoiceTransactions()->orderBy('id')->get();

    expect((float) $lines[0]->collected_snapshot)->toBe(230.0)
        ->and((float) $lines[0]->commission_snapshot)->toBe(15.0)
        ->and((float) $lines[0]->amount_snapshot)->toBe(215.0)
        ->and((float) $lines[1]->collected_snapshot)->toBe(0.0)
        ->and((float) $lines[1]->commission_snapshot)->toBe(15.0)
        ->and((float) $lines[1]->amount_snapshot)->toBe(-15.0);
});

test('the driver invoice pdf states the cash collected and the amount due', function () {
    $order = dischargeOrder($this->seller, $this->driver, $this->city, $this->sector);
    dischargeDelivery($order);

    $invoice = app(DriverInvoiceGeneratorService::class)->generate($this->driver, createdBy: $this->admin);

    $invoice->load([
        'driver',
        'invoiceTransactions.driverTransaction.order.city',
        'invoiceTransactions.driverTransaction.sector',
    ]);

    $lines = $invoice->invoiceTransactions->map(function ($pivot) {
        $tx = $pivot->driverTransaction;

        return (object) [
            'collected_snapshot' => $pivot->collected_snapshot,
            'commission_snapshot' => $pivot->commission_snapshot,
            'amount_snapshot' => $pivot->amount_snapshot,
            'transaction' => $tx,
            'order' => $tx?->order,
            'sector' => $tx?->sector ?? $tx?->order?->sector,
        ];
    });

    $html = view('driver-invoices.pdf', [
        'invoice' => $invoice,
        'driver' => $invoice->driver,
        'lines' => $lines,
        'logo' => null,
        'companyName' => 'SpeedZone Express',
        'adjustments' => ['bonus' => 0.0, 'penalty' => 0.0, 'adjustment' => 0.0],
    ])->render();

    expect($html)
        ->toContain(__('driver_invoices.pdf.collected'))
        ->toContain(__('driver_invoices.pdf.total_due'))
        ->toContain('230.00')
        ->toContain('15.00')
        ->toContain('215.00');
});
