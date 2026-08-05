<?php

use App\Enums\DriverInvoiceStatus;
use App\Enums\DriverTransactionStatus;
use App\Enums\DriverTransactionType;
use App\Models\DriverFinanceLog;
use App\Models\DriverInvoice;
use App\Models\DriverTransaction;
use App\Models\Role;
use App\Models\User;
use App\Services\DriverBillingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->admin = ledgerUser(Role::ADMIN);
    $this->driver = ledgerUser(Role::DRIVER);
});

function ledgerUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id, 'city_id' => null]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

test('an admin records a bonus that lands on the driver pending balance', function () {
    $this->actingAs($this->admin)
        ->post(route('driver-transactions.store'), [
            'driver_id' => $this->driver->id,
            'transaction_type' => DriverTransactionType::BONUS->value,
            'amount' => 150.5,
            'note' => 'Prime de rendement',
        ])
        ->assertRedirect();

    $transaction = DriverTransaction::query()->firstOrFail();

    expect($transaction->transaction_type)->toBe(DriverTransactionType::BONUS)
        ->and($transaction->status)->toBe(DriverTransactionStatus::CONFIRMED)
        ->and((float) $transaction->amount)->toBe(150.5)
        ->and($transaction->note)->toBe('Prime de rendement');

    $summary = app(DriverBillingService::class)->preview($this->driver)['summary'];

    expect($summary['bonus_total'])->toBe(150.5)
        ->and($summary['total_amount'])->toBe(150.5);
});

// The sign belongs to the type, not to the operator: an admin types 40 and the
// ledger decides whether that is owed to the driver or withheld from him.
test('a penalty is stored negative and subtracted from the total', function () {
    $this->actingAs($this->admin)->post(route('driver-transactions.store'), [
        'driver_id' => $this->driver->id,
        'transaction_type' => DriverTransactionType::PENALTY->value,
        'amount' => 40,
    ]);

    expect((float) DriverTransaction::query()->firstOrFail()->amount)->toBe(-40.0);

    $summary = app(DriverBillingService::class)->preview($this->driver)['summary'];

    expect($summary['penalty_total'])->toBe(40.0)
        ->and($summary['total_amount'])->toBe(-40.0);
});

test('a delivery payment cannot be forged through the manual endpoint', function () {
    $this->actingAs($this->admin)
        ->post(route('driver-transactions.store'), [
            'driver_id' => $this->driver->id,
            'transaction_type' => DriverTransactionType::DELIVERY_PAYMENT->value,
            'amount' => 25,
        ])
        ->assertSessionHasErrors('transaction_type');

    expect(DriverTransaction::query()->count())->toBe(0);
});

test('crediting an account that is not a driver is refused', function () {
    $seller = ledgerUser(Role::SELLER);

    $this->actingAs($this->admin)
        ->post(route('driver-transactions.store'), [
            'driver_id' => $seller->id,
            'transaction_type' => DriverTransactionType::BONUS->value,
            'amount' => 25,
        ])
        ->assertNotFound();

    expect(DriverTransaction::query()->count())->toBe(0);
});

test('a driver cannot credit his own ledger', function () {
    $this->actingAs($this->driver)
        ->post(route('driver-transactions.store'), [
            'driver_id' => $this->driver->id,
            'transaction_type' => DriverTransactionType::BONUS->value,
            'amount' => 500,
        ])
        ->assertForbidden();

    expect(DriverTransaction::query()->count())->toBe(0);
});

test('every manual entry leaves an audit trail naming its author', function () {
    $this->actingAs($this->admin)->post(route('driver-transactions.store'), [
        'driver_id' => $this->driver->id,
        'transaction_type' => DriverTransactionType::ADJUSTMENT->value,
        'amount' => 12.75,
        'note' => 'Frais de carburant',
    ]);

    $log = DriverFinanceLog::query()
        ->where('action', DriverFinanceLog::ACTION_ADJUSTMENT)
        ->firstOrFail();

    expect($log->driver_id)->toBe($this->driver->id)
        ->and($log->user_id)->toBe($this->admin->id)
        ->and(json_decode($log->new_value, true))
        ->toMatchArray(['type' => 'ADJUSTMENT', 'amount' => 12.75, 'note' => 'Frais de carburant']);
});

test('a manual entry captured by mistake can be removed while unbilled', function () {
    $transaction = DriverTransaction::query()->create([
        'driver_id' => $this->driver->id,
        'amount' => 80,
        'transaction_type' => DriverTransactionType::BONUS->value,
        'status' => DriverTransactionStatus::CONFIRMED->value,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('driver-transactions.destroy', $transaction))
        ->assertRedirect();

    expect(DriverTransaction::query()->count())->toBe(0)
        ->and(DriverFinanceLog::query()->whereNotNull('old_value')->count())->toBe(1);
});

// Once an invoice carries the amount, the driver has been told what he is owed.
test('a transaction already attached to an invoice is frozen', function () {
    $invoice = DriverInvoice::query()->create([
        'invoice_number' => 'DRV-2026-000001',
        'driver_id' => $this->driver->id,
        'total_amount' => 80,
        'status' => DriverInvoiceStatus::GENERATED->value,
    ]);

    $transaction = DriverTransaction::query()->create([
        'driver_id' => $this->driver->id,
        'driver_invoice_id' => $invoice->id,
        'amount' => 80,
        'transaction_type' => DriverTransactionType::BONUS->value,
        'status' => DriverTransactionStatus::CONFIRMED->value,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('driver-transactions.destroy', $transaction))
        ->assertSessionHas('error');

    expect(DriverTransaction::query()->whereKey($transaction->id)->exists())->toBeTrue();
});

test('a delivery payment cannot be deleted through the manual endpoint', function () {
    $transaction = DriverTransaction::query()->create([
        'driver_id' => $this->driver->id,
        'amount' => 25,
        'transaction_type' => DriverTransactionType::DELIVERY_PAYMENT->value,
        'status' => DriverTransactionStatus::CONFIRMED->value,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('driver-transactions.destroy', $transaction))
        ->assertSessionHas('error');

    expect(DriverTransaction::query()->whereKey($transaction->id)->exists())->toBeTrue();
});
