<?php

use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

test('a vendor creates a product and the creation is recorded in its history', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $this->actingAs($seller)
        ->post('/products', [
            'name' => 'Ceramic mug',
            'sku' => 'MUG-001',
            'barcode' => '3760012345678',
            'category' => 'Kitchen',
            'unit_price' => 79.90,
            'cost_price' => 30,
            'is_fragile' => true,
            'weight_grams' => 450,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $product = Product::acrossStores()->latest('id')->firstOrFail();

    expect($product->name)->toBe('Ceramic mug')
        ->and($product->sku)->toBe('MUG-001')
        ->and($product->is_fragile)->toBeTrue()
        // Stock is never seeded by the catalog form: only the ledger credits it.
        ->and($product->stock_quantity)->toBe(0)
        ->and($product->histories()->count())->toBeGreaterThan(0);
});

test('a product created without a sku gets one generated', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $this->actingAs($seller)
        ->post('/products', [
            'name' => 'Bamboo toothbrush',
            'unit_price' => 25,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(Product::acrossStores()->latest('id')->firstOrFail()->sku)->not->toBeEmpty();
});

test('two products of the same shop cannot share a sku', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    StockFixtures::product($seller, $store, ['sku' => 'DUP-001']);

    $this->actingAs($seller)
        ->post('/products', [
            'name' => 'Another item',
            'sku' => 'DUP-001',
            'unit_price' => 50,
        ])
        ->assertSessionHasErrors('sku');
});

test('editing a product records the field level change with its author', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['name' => 'Old name', 'unit_price' => 100]);

    $this->actingAs($seller)
        ->put("/products/{$product->id}", [
            'name' => 'New name',
            'sku' => $product->sku,
            'unit_price' => 150,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $change = $product->histories()->where('field_name', 'name')->firstOrFail();

    expect($product->fresh()->name)->toBe('New name')
        ->and($change->old_value)->toBe('Old name')
        ->and($change->new_value)->toBe('New name')
        ->and($change->changed_by)->toBe($seller->id);
});

test('a product from another shop is unreachable by direct url', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $shop = StockFixtures::store($seller, 'Shop A');

    $intruder = StockFixtures::user(Role::SELLER);
    $foreignShop = StockFixtures::store($intruder, 'Foreign shop');
    $foreignProduct = StockFixtures::product($intruder, $foreignShop);

    // 404 rather than 403: the store scope runs at route model binding, so the
    // row does not exist as far as this request is concerned.
    $this->actingAs($seller)
        ->get("/products/{$foreignProduct->id}")
        ->assertNotFound();

    expect($shop->id)->not->toBe($foreignShop->id);
});

test('a bulk import credits the declared opening stock through the ledger', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $this->actingAs($seller)
        ->post('/products/import', [
            'products' => [
                ['name' => 'Mug', 'sku' => 'MUG-100', 'unit_price' => 80, 'stock_quantity' => 12],
                ['name' => 'Tee', 'sku' => 'TEE-100', 'unit_price' => 150],
            ],
        ])
        ->assertSessionHasNoErrors();

    $mug = Product::acrossStores()->where('sku', 'MUG-100')->firstOrFail();
    $tee = Product::acrossStores()->where('sku', 'TEE-100')->firstOrFail();

    expect($mug->stock_quantity)->toBe(12)
        // Credited as a movement, so the opening figure is as traceable as any
        // later correction.
        ->and($mug->adjustments()->count())->toBe(1)
        ->and($mug->adjustments()->first()->reason)->toBe(StockAdjustmentReason::INITIAL_STOCK)
        ->and($tee->stock_quantity)->toBe(0)
        ->and($tee->adjustments()->count())->toBe(0);
});

test('a bulk import declaring the same reference twice is refused whole', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $this->actingAs($seller)
        ->post('/products/import', [
            'products' => [
                ['name' => 'First', 'sku' => 'SAME-1', 'unit_price' => 10],
                ['name' => 'Second', 'sku' => 'SAME-1', 'unit_price' => 20],
            ],
        ])
        ->assertSessionHasErrors('products.0.sku');

    expect(Product::acrossStores()->count())->toBe(0);
});

test('a driver cannot reach the catalog at all', function () {
    $driver = StockFixtures::user(Role::DRIVER);

    $this->actingAs($driver)->get('/products')->assertForbidden();
    $this->actingAs($driver)->post('/products', ['name' => 'Nope', 'unit_price' => 10])->assertForbidden();
});

test('only a hub operator may quarantine a product', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $this->actingAs($seller)
        ->put("/products/{$product->id}/block", ['blocked' => true, 'reason' => 'Defective batch'])
        ->assertForbidden();

    $admin = StockFixtures::user(Role::ADMIN);

    $this->actingAs($admin)
        ->put("/products/{$product->id}/block", ['blocked' => true, 'reason' => 'Defective batch'])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->is_blocked)->toBeTrue();
});

test('the movement audit log is reserved to stock admins', function () {
    $this->actingAs(StockFixtures::user(Role::SELLER))->get('/stock/movements')->assertForbidden();
    $this->actingAs(StockFixtures::user(Role::ADMIN))->get('/stock/movements')->assertOk();
});
