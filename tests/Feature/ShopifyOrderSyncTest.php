<?php

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use App\Enums\OrderCreationSource;
use App\Enums\ShopifyImportStatus;
use App\Models\City;
use App\Models\EcommerceIntegration;
use App\Models\EcommerceIntegrationSync;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->withoutVite();

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    Http::preventStrayRequests();
});

function shopifySyncSeller(): User
{
    return StockFixtures::user(Role::SELLER);
}

function shopifySyncStore(User $owner): Store
{
    return StockFixtures::store($owner);
}

function shopifySyncCity(string $name = 'Casablanca'): City
{
    $city = City::query()->create([
        'name' => $name,
        'code' => strtoupper(substr($name, 0, 8)).'-'.uniqid(),
        'region' => 'Casablanca-Settat',
        'is_active' => true,
    ]);

    Sector::query()->create([
        'city_id' => $city->id,
        'name' => 'Centre',
        'delivery_price' => 35,
        'return_price' => 15,
        'is_active' => true,
    ]);

    return $city;
}

function connectedShopify(User $seller, Store $store): EcommerceIntegration
{
    return EcommerceIntegration::query()->create([
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'platform' => EcommercePlatform::Shopify,
        'status' => EcommerceIntegrationStatus::Connected,
        'shop_slug' => 'atlas.myshopify.com',
        'shop_name' => 'Atlas Shopify',
        'external_store_id' => '998877',
        'access_token' => 'shpat_test_token',
        'connected_at' => now(),
        'connected_by' => $seller->id,
        'import_status' => ShopifyImportStatus::Unfulfilled->value,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $orders
 * @param  array<string, mixed>  $options
 */
function fakeShopifyOrders(array $orders, array $options = []): void
{
    Http::fake(function (Request $request) use ($orders, $options) {
        $url = $request->url();
        $token = $request->header('X-Shopify-Access-Token')[0] ?? null;

        if ($token !== 'shpat_test_token') {
            return Http::response(['errors' => '[API] Invalid API key or access token'], 401);
        }

        if (str_contains($url, '/orders.json')) {
            $headers = [];

            if (($options['next'] ?? false) === true) {
                $headers['Link'] = '<https://atlas.myshopify.com/admin/api/2026-07/orders.json?page_info=cursor-2&limit=50>; rel="next"';
            }

            return Http::response(['orders' => $orders], 200, $headers);
        }

        return Http::response(['errors' => 'unexpected '.$url], 500);
    });
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shopifyOrder(array $overrides = []): array
{
    return array_merge([
        'id' => 1001,
        'name' => '#1001',
        'order_number' => 1001,
        'total_price' => '199.00',
        'created_at' => now()->toIso8601String(),
        'updated_at' => now()->toIso8601String(),
        'financial_status' => 'pending',
        'fulfillment_status' => null,
        'status' => 'open',
        'note' => 'Leave at the door',
        'shipping_address' => [
            'first_name' => 'Sara',
            'last_name' => 'Benali',
            'phone' => '0612345678',
            'city' => 'Casablanca',
            'address1' => '12 rue Maarif',
        ],
        'customer' => [
            'first_name' => 'Sara',
            'last_name' => 'Benali',
            'phone' => '0612345678',
        ],
        'note_attributes' => [
            ['name' => 'Ville', 'value' => 'Casablanca'],
        ],
    ], $overrides);
}

function commitShopifyReview($test, User $seller): EcommerceIntegrationSync
{
    $sync = EcommerceIntegrationSync::query()->latest('id')->first();
    $payload = $sync->rows()->get()->map(function ($row) {
        $values = $row->values ?? [];

        return array_merge($values, [
            'id' => $row->id,
            'is_fragile' => (bool) ($values['is_fragile'] ?? false),
            'can_be_opened' => (bool) ($values['can_be_opened'] ?? false),
            'option_exchange' => (bool) ($values['option_exchange'] ?? false),
            'delivery_included' => (bool) ($values['delivery_included'] ?? false),
        ]);
    })->all();

    $test->actingAs($seller)
        ->post(route('integrations.shopify.review.store', $sync), ['orders' => $payload])
        ->assertRedirect();

    return $sync->refresh();
}

it('stages an unfulfilled Shopify order for review then imports it as a parcel', function () {
    shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);

    fakeShopifyOrders([shopifyOrder()]);

    $this->actingAs($seller)
        ->post(route('integrations.sync', $integration))
        ->assertRedirect(route('integrations.shopify.review', EcommerceIntegrationSync::query()->first()))
        ->assertSessionHas('success');

    expect(Order::query()->count())->toBe(0);

    $sync = commitShopifyReview($this, $seller);
    $order = Order::query()->first();

    expect($order)->not->toBeNull()
        ->and($order->creation_source)->toBe(OrderCreationSource::Integration)
        ->and($order->ecommerce_integration_id)->toBe($integration->id)
        ->and($order->external_order_id)->toBe('1001')
        ->and($order->ecommerce_order_ref)->toBe('#1001')
        ->and($order->customer_first_name)->toBe('Sara')
        ->and($order->customer_phone)->toBe('0612345678')
        ->and((float) $order->order_amount)->toBe(199.0)
        ->and($order->store_id)->toBe($store->id);

    expect($sync)->not->toBeNull()
        ->and($sync->status)->toBe(EcommerceSyncStatus::Succeeded)
        ->and($sync->trigger)->toBe(EcommerceSyncTrigger::Manual)
        ->and($sync->created_count)->toBe(1)
        ->and($order->ecommerce_sync_id)->toBe($sync->id);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'atlas.myshopify.com/admin/api/2026-07/orders.json')
        && $request->header('X-Shopify-Access-Token') === ['shpat_test_token']
        && str_contains($request->url(), 'status=any'));
});

it('skips Shopify orders that do not match the unfulfilled import filter', function () {
    shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);

    fakeShopifyOrders([shopifyOrder([
        'id' => 2002,
        'name' => '#2002',
        'fulfillment_status' => 'fulfilled',
    ])]);

    $this->actingAs($seller)
        ->post(route('integrations.sync', $integration))
        ->assertRedirect(route('integrations.shopify', ['store_id' => $store->id]));

    $sync = EcommerceIntegrationSync::query()->first();

    expect(Order::query()->count())->toBe(0)
        ->and($sync->status)->toBe(EcommerceSyncStatus::Succeeded)
        ->and($sync->fetched_count)->toBe(1)
        ->and($sync->skipped_count)->toBe(1)
        ->and($sync->created_count)->toBe(0);
});
