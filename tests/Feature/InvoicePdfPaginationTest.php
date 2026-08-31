<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\InvoiceGeneratorService;
use App\Services\PdfInvoiceService;
use App\Support\PdfPageNumbering;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;

/**
 * A settlement of sixty orders does not fit on one sheet, and for a long time
 * the extra sheets were the ones nobody looked at: the rows ran under the fixed
 * footer and no page carried a number. These tests render a genuinely long
 * invoice and read the produced PDF back.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->city = City::query()->create([
        'name' => 'Paper City',
        'code' => 'PAP',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $this->sector = Sector::query()->create([
        'city_id' => $this->city->id,
        'name' => 'Paper Sector',
        'delivery_price' => 30,
        'return_price' => 12,
        'is_active' => true,
    ]);

    $this->seller = invoicePdfUser(Role::SELLER);
    $this->admin = invoicePdfUser(Role::ADMIN);
});

function invoicePdfUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id, 'city_id' => null]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function invoicePdfOrders(User $seller, City $city, Sector $sector, int $count): void
{
    foreach (range(1, $count) as $i) {
        Order::query()->create([
            'tracking_number' => 'PDF-2026-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            'seller_id' => $seller->id,
            'customer_first_name' => 'Client',
            'customer_last_name' => 'Numéro '.$i,
            'customer_phone' => '0611111111',
            'customer_address' => '12 Paper Street',
            'city_id' => $city->id,
            'sector_id' => $sector->id,
            'payment_method' => PaymentMethod::CASH->value,
            'order_amount' => 200,
            'delivery_price' => 30,
            'status' => OrderStatus::DELIVERED->value,
            'delivered_at' => now()->subDays(2),
        ]);
    }
}

test('a long invoice runs over several pages and still produces a document', function () {
    invoicePdfOrders($this->seller, $this->city, $this->sector, 60);

    $invoice = app(InvoiceGeneratorService::class)->generate($this->seller, createdBy: $this->admin);

    expect($invoice)->not->toBeNull();

    $pdf = app(PdfInvoiceService::class)->build($invoice);

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBeGreaterThan(1)
        ->and($pdf->output())->toStartWith('%PDF-');
});

test('a short invoice stays on one page', function () {
    invoicePdfOrders($this->seller, $this->city, $this->sector, 3);

    $invoice = app(InvoiceGeneratorService::class)->generate($this->seller, createdBy: $this->admin);

    $pdf = app(PdfInvoiceService::class)->build($invoice);

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
});

/**
 * The marker itself cannot be read back out of the produced file — the font is
 * embedded with an Identity encoding, so the glyphs are indices, not letters.
 * It is asserted where it is written instead.
 */
test('every page is stamped with its own number', function () {
    $written = [];

    $canvas = Mockery::mock(Canvas::class);
    $canvas->shouldReceive('get_width')->andReturn(595.28);
    $canvas->shouldReceive('get_height')->andReturn(841.89);
    $canvas->shouldReceive('page_script')->once()->andReturnUsing(function (callable $script) {
        foreach (range(1, 3) as $page) {
            $script($page, 3);
        }
    });
    $canvas->shouldReceive('text')->andReturnUsing(function ($x, $y, $text) use (&$written) {
        $written[] = $text;
    });

    $metrics = Mockery::mock(FontMetrics::class);
    $metrics->shouldReceive('getFont')->andReturn('DejaVu Sans');
    $metrics->shouldReceive('getTextWidth')->andReturn(40.0);

    $dompdf = Mockery::mock(Dompdf::class);
    $dompdf->shouldReceive('getCanvas')->andReturn($canvas);
    $dompdf->shouldReceive('getFontMetrics')->andReturn($metrics);

    $pdf = Mockery::mock(PdfInstance::class);
    $pdf->shouldReceive('render')->once();
    $pdf->shouldReceive('getDomPDF')->andReturn($dompdf);

    PdfPageNumbering::stamp($pdf, 'invoices.pdf.page_of');

    expect($written)->toBe(['Page 1 / 3', 'Page 2 / 3', 'Page 3 / 3']);
});
