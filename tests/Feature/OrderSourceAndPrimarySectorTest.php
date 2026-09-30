<?php

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\OrderCreationSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShopifyImportStatus;
use App\Models\City;
use App\Models\EcommerceIntegration;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\Ecommerce\YouCan\YouCanOrderMapper;
use App\Services\SectorService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->withoutVite();

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function primaryCity(string $name = 'Casa'): City
{
    return City::query()->create([
        'name' => $name,
        'code' => strtoupper(substr($name, 0, 8)).'-'.uniqid(),
        'region' => 'Casablanca-Settat',
        'is_active' => true,
    ]);
}

function primarySector(City $city, string $name, bool $isPrimary = false): Sector
{
    return Sector::query()->create([
        'city_id' => $city->id,
        'name' => $name,
        'delivery_price' => 30,
        'is_active' => true,
        'is_primary' => $isPrimary,
    ]);
}

function primaryUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

it('keeps a single primary sector per city', function () {
    $city = primaryCity();
    $first = primarySector($city, 'Maarif', true);
    $second = primarySector($city, 'Anfa');

    app(SectorService::class)->update($second, ['is_primary' => true]);

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->fresh()->is_primary)->toBeTrue();
});

it('fills a missing import sector with the city primary sector', function () {
    $city = primaryCity();
    primarySector($city, 'Maarif');
    $primary = primarySector($city, 'Centre', true);
    $seller = primaryUser(Role::SELLER);

    $this->actingAs($seller)->post(route('orders.import.store'), [
        'orders' => [[
            'customer_first_name' => 'Yasmine',
            'customer_last_name' => 'El Amrani',
            'customer_phone' => '0612345678',
            'customer_address' => '12 rue des Fleurs',
            'city_id' => $city->id,
            'sector_id' => null,
            'payment_method' => PaymentMethod::CASH->value,
            'order_amount' => 450,
            'is_fragile' => false,
            'can_be_opened' => false,
            'option_exchange' => false,
        ]],
    ])->assertRedirect(route('orders.index'));

    expect(Order::query()->value('sector_id'))->toBe($primary->id);
});

it('uses the primary sector when a YouCan order has no quartier', function () {
    $city = primaryCity('Casablanca');
    primarySector($city, 'Maarif');
    $primary = primarySector($city, 'Centre', true);

    $values = app(YouCanOrderMapper::class)->resolveValues([
        'customer_first_name' => 'Sara',
        'customer_last_name' => 'Benali',
        'customer_phone' => '0612345678',
        'customer_address' => '12 rue Maarif',
        'city_id' => 'Casablanca',
        'sector_id' => '',
        'payment_method' => '',
        'order_amount' => '199',
        'notes' => '',
        'is_fragile' => '',
        'can_be_opened' => '',
        'option_exchange' => '',
        'delivery_included' => '',
    ]);

    expect($values['city_id'])->toBe($city->id)
        ->and($values['sector_id'])->toBe($primary->id);
});

it('shows Shopify instead of Intégration on the orders list', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $city = primaryCity();
    $integration = EcommerceIntegration::query()->create([
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'platform' => EcommercePlatform::Shopify,
        'status' => EcommerceIntegrationStatus::Connected,
        'shop_slug' => 'atlas.myshopify.com',
        'shop_name' => 'Atlas Shopify',
        'access_token' => 'shpat_test_token',
        'connected_at' => now(),
        'connected_by' => $seller->id,
        'import_status' => ShopifyImportStatus::Unfulfilled->value,
    ]);

    Order::query()->create([
        'tracking_number' => 'SZ-SRC-1',
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'customer_first_name' => 'Sara',
        'customer_last_name' => 'Benali',
        'customer_phone' => '0612345678',
        'customer_address' => '12 rue Maarif',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 199,
        'delivery_price' => 35,
        'status' => OrderStatus::CREATED->value,
        'creation_source' => OrderCreationSource::Integration->value,
        'ecommerce_integration_id' => $integration->id,
    ]);

    $this->actingAs(primaryUser(Role::ADMIN))
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('orders.data.0.creation_source', 'integration')
            ->where('orders.data.0.creation_source_label', 'Shopify')
            ->where('orders.data.0.creation_source_icon', 'ri-shopping-bag-3-fill'));
});

it('lets a guest switch the auth pages to English', function () {
    $this->from(route('login'))
        ->post(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('login'));

    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/ForgotPassword')
            ->where('locale', 'en'));
});
