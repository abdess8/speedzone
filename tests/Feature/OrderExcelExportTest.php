<?php

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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->city = City::query()->create([
        'name' => 'Export City',
        'code' => 'EXC',
        'region' => 'Test',
        'is_active' => true,
    ]);
});

function exportUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function exportOrder(User $seller, City $city, array $attributes = []): Order
{
    return Order::query()->create(array_merge([
        'tracking_number' => 'EXP-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0612345678',
        'customer_address' => '12 Export Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 250,
        'delivery_price' => 30,
        'status' => OrderStatus::OUT_FOR_DELIVERY->value,
    ], $attributes))->fresh();
}

/**
 * Download the export and read it back the way Excel would.
 */
function readExport($test): array
{
    $response = $test->get(route('orders.export'));

    $response->assertOk();

    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $sheet = IOFactory::load($path)->getActiveSheet();

    return [$sheet, $path];
}

it('hands back a workbook and not a comma soup', function () {
    $seller = exportUser(Role::SELLER);
    exportOrder($seller, $this->city);

    $response = $this->actingAs(exportUser(Role::ADMIN))->get(route('orders.export'));

    $response->assertOk();

    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml.sheet')
        ->and($response->headers->get('content-disposition'))
        ->toContain('.xlsx');
});

it('names every column in the operator language', function () {
    $seller = exportUser(Role::SELLER);
    exportOrder($seller, $this->city);

    $this->actingAs(exportUser(Role::ADMIN));

    [$sheet, $path] = readExport($this);

    $header = $sheet->rangeToArray('A1:R1')[0];

    expect($header[0])->toBe(__('orders.export.tracking_number'))
        ->and($header)->toContain(__('orders.export.total_amount'))
        ->and($header)->toContain(__('orders.export.failure_reason'))
        ->and($header)->not->toContain(null);

    unlink($path);
});

it('writes money as a number Excel can add up', function () {
    $seller = exportUser(Role::SELLER);
    exportOrder($seller, $this->city);

    $this->actingAs(exportUser(Role::ADMIN));

    [$sheet, $path] = readExport($this);

    $total = $sheet->getCell('O2');

    expect($total->getValue())->toBeNumeric()
        ->and((float) $total->getValue())->toEqual(280.0)
        ->and($total->getStyle()->getNumberFormat()->getFormatCode())->toContain('MAD');

    unlink($path);
});

it('keeps the leading zero of a phone number', function () {
    $seller = exportUser(Role::SELLER);
    exportOrder($seller, $this->city);

    $this->actingAs(exportUser(Role::ADMIN));

    [$sheet, $path] = readExport($this);

    expect($sheet->getCell('G2')->getValue())->toBe('0612345678');

    unlink($path);
});

it('writes a date as a date', function () {
    $seller = exportUser(Role::SELLER);
    $order = exportOrder($seller, $this->city);

    $this->actingAs(exportUser(Role::ADMIN));

    [$sheet, $path] = readExport($this);

    $cell = $sheet->getCell('B2');

    expect($cell->getValue())->toBeNumeric()
        ->and(ExcelDate::excelToDateTimeObject($cell->getValue())->format('Y-m-d'))
        ->toBe($order->created_at->format('Y-m-d'));

    unlink($path);
});

it('carries the motif of a missed attempt in its own column', function () {
    $seller = exportUser(Role::SELLER);
    exportOrder($seller, $this->city, [
        'failure_reason' => OrderFailureReason::CUSTOMER_UNREACHABLE->value,
        'failed_attempts_count' => 2,
    ]);

    $this->actingAs(exportUser(Role::ADMIN));

    [$sheet, $path] = readExport($this);

    expect($sheet->getCell('C2')->getValue())->toBe(OrderStatus::OUT_FOR_DELIVERY->label())
        ->and($sheet->getCell('D2')->getValue())->toBe(OrderFailureReason::CUSTOMER_UNREACHABLE->label())
        ->and((int) $sheet->getCell('E2')->getValue())->toBe(2);

    unlink($path);
});

it('exports only what the filter was showing', function () {
    $seller = exportUser(Role::SELLER);

    $kept = exportOrder($seller, $this->city, ['status' => OrderStatus::DELIVERED->value]);
    exportOrder($seller, $this->city, ['status' => OrderStatus::OUT_FOR_DELIVERY->value]);

    $response = $this->actingAs(exportUser(Role::ADMIN))
        ->get(route('orders.export', ['status' => OrderStatus::DELIVERED->value]));

    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $rows = IOFactory::load($path)->getActiveSheet()->toArray();

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe($kept->tracking_number);

    unlink($path);
});

it('never shows a seller somebody else parcels', function () {
    $mine = exportUser(Role::SELLER);
    $other = exportUser(Role::SELLER);

    $own = exportOrder($mine, $this->city);
    exportOrder($other, $this->city);

    $this->actingAs($mine);

    [$sheet, $path] = readExport($this);

    $rows = $sheet->toArray();

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe($own->tracking_number);

    unlink($path);
});
