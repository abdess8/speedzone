<?php

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use App\Enums\OrderCreationSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
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

        $path = strtok($url, '?') ?: $url;

        if (preg_match('#/orders/(\d+)\.json$#', $path, $matches)) {
            $id = $matches[1];

            foreach ($orders as $order) {
                if ((string) ($order['id'] ?? '') === $id) {
                    return Http::response(['order' => $order], 200);
                }
            }

            return Http::response(['errors' => 'Not Found'], 404);
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

it('creates SpeedZone parcels from Shopify on sync now', function () {
    shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);

    fakeShopifyOrders([shopifyOrder()]);

    $this->actingAs($seller)
        ->post(route('integrations.sync', $integration))
        ->assertRedirect(route('integrations.shopify', ['store_id' => $store->id]))
        ->assertSessionHas('success');

    $order = Order::query()->first();
    $sync = EcommerceIntegrationSync::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->creation_source)->toBe(OrderCreationSource::Integration)
        ->and($order->ecommerce_integration_id)->toBe($integration->id)
        ->and($order->external_order_id)->toBe('1001')
        ->and($order->ecommerce_order_ref)->toBe('#1001')
        ->and($order->customer_first_name)->toBe('Sara')
        ->and($order->customer_phone)->toBe('0612345678')
        ->and((float) $order->order_amount)->toBe(199.0)
        ->and($order->store_id)->toBe($store->id)
        ->and($sync->status)->toBe(EcommerceSyncStatus::Succeeded)
        ->and($sync->trigger)->toBe(EcommerceSyncTrigger::Manual)
        ->and($sync->created_count)->toBe(1)
        ->and($order->ecommerce_sync_id)->toBe($sync->id);
});

it('pushes mapped SpeedZone statuses to Shopify on sync now', function () {
    $city = shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);
    $integration->forceFill([
        'status_mapping' => [
            'OUT_FOR_DELIVERY' => 'out_for_delivery',
        ],
    ])->save();

    shopifyImportedOrder($seller, $store, $integration, $city, [
        'status' => OrderStatus::OUT_FOR_DELIVERY->value,
        'external_order_id' => '2002',
    ]);

    Http::fake(function (Request $request) {
        $url = $request->url();
        $method = $request->method();

        if (str_contains($url, '/admin/api/') && str_ends_with(strtok($url, '?') ?: $url, '/orders.json')) {
            return Http::response(['orders' => []]);
        }

        if (str_contains($url, '/fulfillment_orders.json')) {
            return Http::response([
                'fulfillment_orders' => [
                    ['id' => 555001, 'status' => 'open'],
                ],
            ]);
        }

        if ($method === 'POST' && preg_match('#/fulfillments\\.json#', $url) && ! str_contains($url, '/events')) {
            return Http::response(['fulfillment' => ['id' => 777]]);
        }

        if (str_contains($url, '/events.json')) {
            return Http::response(['fulfillment_event' => ['id' => 1, 'status' => 'out_for_delivery']]);
        }

        if (str_contains($url, '/fulfillments.json')) {
            return Http::response(['fulfillments' => []]);
        }

        return Http::response(['errors' => 'unexpected '.$url], 500);
    });

    $this->actingAs($seller)
        ->post(route('integrations.sync', $integration))
        ->assertRedirect(route('integrations.shopify', ['store_id' => $store->id]));

    $sync = EcommerceIntegrationSync::query()->latest('id')->first();

    expect($sync->created_count)->toBe(0)
        ->and($sync->updated_count)->toBe(1);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/orders/2002/fulfillment_orders.json')
        || str_contains($request->url(), '/fulfillments.json')
        || str_contains($request->url(), '/events.json'));
});

it('refreshes an expired Shopify client-credentials token before syncing orders', function () {
    shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);
    $integration->forceFill([
        'client_id' => 'shopify-client-id',
        'client_secret' => 'shopify-client-secret',
        'access_token' => 'shpat_expired',
        'token_expires_at' => now()->subMinute(),
    ])->save();

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/admin/oauth/access_token')) {
            return Http::response([
                'access_token' => 'shpat_test_token',
                'scope' => 'read_orders',
                'expires_in' => 86399,
            ]);
        }

        if (($request->header('X-Shopify-Access-Token')[0] ?? null) !== 'shpat_test_token') {
            return Http::response(['errors' => '[API] Invalid API key or access token'], 401);
        }

        if (str_contains($url, '/orders.json')) {
            return Http::response(['orders' => [shopifyOrder()]]);
        }

        return Http::response(['errors' => 'unexpected '.$url], 500);
    });

    $this->actingAs($seller)
        ->post(route('integrations.sync', $integration))
        ->assertRedirect();

    expect($integration->refresh()->access_token)->toBe('shpat_test_token')
        ->and($integration->token_expires_at?->isFuture())->toBeTrue();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/admin/oauth/access_token'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/orders.json')
        && $request->header('X-Shopify-Access-Token') === ['shpat_test_token']);
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

/**
 * @param  array<string, mixed>  $overrides
 */
function shopifyImportedOrder(
    User $seller,
    Store $store,
    EcommerceIntegration $integration,
    City $city,
    array $overrides = [],
): Order {
    return Order::query()->create(array_merge([
        'tracking_number' => 'SZ-SHOP-'.uniqid(),
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
        'status' => OrderStatus::OUT_FOR_DELIVERY->value,
        'creation_source' => OrderCreationSource::Integration->value,
        'ecommerce_integration_id' => $integration->id,
        'external_order_id' => '1001',
        'ecommerce_order_ref' => '#1001',
    ], $overrides));
}

it('fulfills the Shopify order when the parcel reaches a mapped SpeedZone status', function () {
    $city = shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);
    $integration->forceFill([
        'status_mapping' => [
            'DELIVERED' => 'fulfilled',
        ],
    ])->save();

    $order = shopifyImportedOrder($seller, $store, $integration, $city);

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/fulfillment_orders.json')) {
            return Http::response([
                'fulfillment_orders' => [
                    ['id' => 555001, 'status' => 'open'],
                ],
            ]);
        }

        if ($request->method() === 'POST' && str_contains($url, '/fulfillments.json')) {
            expect($request->data()['fulfillment']['line_items_by_fulfillment_order'][0]['fulfillment_order_id'] ?? null)
                ->toBe(555001);

            return Http::response(['fulfillment' => ['id' => 777]]);
        }

        return Http::response(['errors' => 'unexpected '.$url], 500);
    });

    $order->update(['status' => OrderStatus::DELIVERED->value]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/orders/1001/fulfillment_orders.json'));
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/fulfillments.json'));
});

it('cancels the Shopify order when the mapped SpeedZone status is CANCELED', function () {
    $city = shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);
    $integration->forceFill([
        'status_mapping' => [
            'CANCELED' => 'cancelled',
        ],
    ])->save();

    $order = shopifyImportedOrder($seller, $store, $integration, $city);

    Http::fake(function (Request $request) {
        if ($request->method() === 'POST' && str_contains($request->url(), '/orders/1001/cancel.json')) {
            return Http::response(['order' => ['id' => 1001, 'cancelled_at' => now()->toIso8601String()]]);
        }

        return Http::response(['errors' => 'unexpected '.$request->url()], 500);
    });

    $order->update(['status' => OrderStatus::CANCELED->value]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/orders/1001/cancel.json'));
});

it('does not call Shopify when the SpeedZone status is not mapped', function () {
    $city = shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);
    $integration->forceFill([
        'status_mapping' => [
            'DELIVERED' => 'fulfilled',
        ],
    ])->save();

    $order = shopifyImportedOrder($seller, $store, $integration, $city, [
        'status' => OrderStatus::IN_DELIVERY_CITY->value,
    ]);

    Http::fake();

    $order->update(['status' => OrderStatus::OUT_FOR_DELIVERY->value]);

    Http::assertNothingSent();
});

it('retries failed Shopify orders from the history tab', function () {
    shopifySyncCity();
    $seller = shopifySyncSeller();
    $store = shopifySyncStore($seller);
    $integration = connectedShopify($seller, $store);
    $failed = shopifyOrder([
        'id' => 4001,
        'name' => '#4001',
        'shipping_address' => [
            'first_name' => 'Sara',
            'last_name' => 'Benali',
            'phone' => '0612345678',
            'city' => 'Atlantis',
            'address1' => '12 rue Maarif',
        ],
        'note_attributes' => [
            ['name' => 'Ville', 'value' => 'Atlantis'],
        ],
    ]);

    fakeShopifyOrders([$failed]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));

    $source = EcommerceIntegrationSync::query()->latest('id')->first();

    expect(Order::query()->count())->toBe(0)
        ->and($source->error_count)->toBe(1);

    shopifySyncCity('Atlantis');

    $this->actingAs($seller)
        ->post(route('integrations.sync.retry', [$integration, $source]))
        ->assertRedirect(route('integrations.shopify', ['store_id' => $store->id, 'tab' => 'history']))
        ->assertSessionHas('success');

    $retry = EcommerceIntegrationSync::query()->latest('id')->first();

    expect(Order::query()->value('external_order_id'))->toBe('4001')
        ->and($retry->trigger)->toBe(EcommerceSyncTrigger::Retry)
        ->and($retry->created_count)->toBe(1)
        ->and($source->fresh()->error_count)->toBe(0);
});
