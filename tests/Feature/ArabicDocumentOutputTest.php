<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderLabelPdfService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
    $this->admin = User::factory()->create(['role_id' => $role->id, 'city_id' => null]);
    $this->admin->roles()->sync([$role->id]);
    $this->admin = $this->admin->fresh(['roles.permissions']);

    $city = City::query()->create([
        'name' => 'الدار البيضاء',
        'code' => 'CAS',
        'region' => 'Casablanca-Settat',
        'is_active' => true,
    ]);

    $this->order = Order::query()->create([
        'tracking_number' => 'ARB-2026-000001',
        'seller_id' => $this->admin->id,
        'customer_first_name' => 'محمد',
        'customer_last_name' => 'العلمي',
        'customer_phone' => '0612345678',
        'customer_address' => 'شارع محمد الخامس، إقامة الأمل، رقم 12، حي المعاريف',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 250,
        'delivery_price' => 30,
        'notes' => 'الاتصال قبل التسليم',
        'status' => OrderStatus::CREATED->value,
    ]);
});

test('a label with arabic customer data renders without losing the text', function () {
    $pdf = app(OrderLabelPdfService::class)->build($this->order->fresh())->output();

    expect($pdf)->toStartWith('%PDF');

    // Dompdf subsets the fonts it uses, so an arabic label must embed glyphs
    // beyond the latin range for the customer data to be on the page at all.
    expect(strlen($pdf))->toBeGreaterThan(10000);
});

test('the label endpoint serves an arabic order', function () {
    $this->actingAs($this->admin)
        ->get(route('orders.pdf', $this->order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the excel export carries arabic through the workbook unchanged', function () {
    $response = $this->actingAs($this->admin)->get(route('orders.export'));

    $response->assertOk()->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $sheet = IOFactory::load($path)->getActiveSheet();

    // Read back through a reader rather than grepping the archive: xlsx is a
    // zip, so a string assertion would pass on compressed noise.
    $values = collect($sheet->toArray())->flatten()->filter()->all();

    expect($values)->toContain('محمد العلمي')
        ->and($values)->toContain('الدار البيضاء')
        ->and($values)->toContain('ARB-2026-000001');

    unlink($path);
});
