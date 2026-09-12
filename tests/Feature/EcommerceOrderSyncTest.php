<?php

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\EcommerceSyncRowStatus;
use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use App\Enums\OrderCreationSource;
use App\Enums\YouCanImportStatus;
use App\Models\City;
use App\Models\EcommerceIntegration;
use App\Models\EcommerceIntegrationSync;
use App\Models\EcommerceIntegrationSyncRow;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use App\Services\Ecommerce\EcommerceOrderSyncService;
use App\Services\TeamRoleService;
use App\Services\TeamService;
use App\Support\EcommerceIntegrationPermissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
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

function syncSeller(): User
{
    return StockFixtures::user(Role::SELLER);
}

function syncStore(User $owner): Store
{
    return StockFixtures::store($owner);
}

function syncCity(string $name = 'Casablanca'): City
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

function connectedYouCan(User $seller, Store $store): EcommerceIntegration
{
    return EcommerceIntegration::query()->create([
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'platform' => EcommercePlatform::YouCan,
        'status' => EcommerceIntegrationStatus::Connected,
        'shop_slug' => 'atlas',
        'shop_name' => 'Atlas Concept',
        'external_store_id' => 'store-uuid-1',
        'email' => 'seller@youcan.test',
        'client_secret' => 'youcan-password',
        'access_token' => 'sso:store-uuid-1',
        'connected_at' => now(),
        'connected_by' => $seller->id,
        'import_status' => YouCanImportStatus::Open->value,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $orders
 * @param  array<string, mixed>  $options
 */
function fakeYouCanOrders(array $orders, array $options = []): void
{
    $stores = $options['stores'] ?? [[
        'id' => 'store-uuid-1',
        'slug' => 'atlas',
        'name' => 'Atlas Concept',
        'active' => true,
    ]];

    Http::fake(function (Request $request) use ($orders, $stores) {
        $url = $request->url();

        if (str_contains($url, 'youcan-idp/authenticate')) {
            return Http::response('ok', 302, [
                'Location' => 'https://accounts.youcan.shop/sso/login?session_id=sso-test-session',
            ]);
        }

        if (str_contains($url, '/sanctum/csrf-cookie')) {
            return Http::response('', 204, [
                'Set-Cookie' => 'XSRF-TOKEN-ACCOUNTS=test-xsrf; Path=/; Domain=.youcan.shop',
            ]);
        }

        if (str_contains($url, '/sso/login') && $request->method() === 'POST') {
            return Http::response('ok', 302, [
                'Location' => 'https://accounts.youcan.shop/redirect?to=https://seller-area.youcan.shop',
            ]);
        }

        if (str_contains($url, '/shop/stores')) {
            return Http::response(['data' => $stores], 200);
        }

        if (preg_match('#/admin/([^/]+)/switch-store#', $url)) {
            return Http::response('switched', 200);
        }

        if (str_contains($url, '/admin/web/api/orders')) {
            return Http::response(['data' => $orders, 'meta' => ['last_page' => 1]], 200);
        }

        if (str_contains($url, 'get-dotshop-object')) {
            return Http::response([
                'store' => ['id' => 'store-uuid-1', 'slug' => 'atlas', 'name' => 'Atlas Concept'],
                'owner' => ['email' => 'seller@youcan.test'],
            ], 200);
        }

        if (str_contains($url, 'seller-area.youcan.shop/admin')) {
            return Http::response('ok', 200);
        }

        return Http::response(['detail' => 'unexpected '.$url], 500);
    });
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function youCanOrder(array $overrides = []): array
{
    return array_merge([
        'id' => 'order-uuid-1',
        'ref' => 'YC-1001',
        'total' => 199,
        'created_at' => now()->toIso8601String(),
        'status_new' => ['slug' => 'open'],
        'payment' => ['status' => ['slug' => 'unpaid']],
        'extra_fields' => [
            ['name' => 'Nom Complet', 'value' => 'Sara Benali'],
            ['name' => 'Téléphone', 'value' => '0612345678'],
            ['name' => 'Ville', 'value' => 'Casablanca'],
            ['name' => 'Adresse', 'value' => '12 rue Maarif'],
        ],
    ], $overrides);
}

function commitYouCanReview($test, User $seller): EcommerceIntegrationSync
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
        ->post(route('integrations.youcan.review.store', $sync), ['orders' => $payload])
        ->assertRedirect();

    return $sync->refresh();
}

it('imports an open YouCan order as a SpeedZone parcel', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder()]);

    $this->actingAs($seller)
        ->post(route('integrations.sync', $integration))
        ->assertRedirect(route('integrations.youcan.review', EcommerceIntegrationSync::query()->first()))
        ->assertSessionHas('success');

    expect(Order::query()->count())->toBe(0);

    $sync = commitYouCanReview($this, $seller);
    $order = Order::query()->first();

    expect($order)->not->toBeNull()
        ->and($order->creation_source)->toBe(OrderCreationSource::Integration)
        ->and($order->ecommerce_integration_id)->toBe($integration->id)
        ->and($order->external_order_id)->toBe('order-uuid-1')
        ->and($order->ecommerce_order_ref)->toBe('YC-1001')
        ->and($order->customer_first_name)->toBe('Sara')
        ->and($order->customer_phone)->toBe('0612345678')
        ->and((float) $order->order_amount)->toBe(199.0)
        ->and($order->store_id)->toBe($store->id);

    $sync = EcommerceIntegrationSync::query()->first();

    expect($sync)->not->toBeNull()
        ->and($sync->status)->toBe(EcommerceSyncStatus::Succeeded)
        ->and($sync->trigger)->toBe(EcommerceSyncTrigger::Manual)
        ->and($sync->created_count)->toBe(1)
        ->and($order->ecommerce_sync_id)->toBe($sync->id);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/admin/web/api/orders')
        && str_contains($request->url(), 'include=customer')
        && $request->header('X-Requested-With') === ['XMLHttpRequest']
        && str_contains((string) $request->header('User-Agent')[0], 'Mozilla/5.0'));
});

it('imports YouCan orders older than seven days on the first catalog scan', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder([
        'id' => 'old-open-1',
        'created_at' => now()->subYear()->toIso8601String(),
    ])]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    commitYouCanReview($this, $seller);

    expect(Order::query()->value('external_order_id'))->toBe('old-open-1');

    $sync = EcommerceIntegrationSync::query()->first();

    expect($sync->fetched_count)->toBe(1)
        ->and($sync->created_count)->toBe(1);
});

it('imports a YouCan order whose extra fields are a bilingual map', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder([
        'id' => 'map-extra-1',
        'extra_fields' => [
            'Nom Complet / الاسم الكامل' => 'Sara Benali',
            'Téléphone / الهاتف' => '0612345678',
            'Ville / مدينتك' => 'Casablanca',
            'Votre Adresse / عنوانك' => '12 rue Maarif',
        ],
    ])]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    commitYouCanReview($this, $seller);

    expect(Order::query()->value('external_order_id'))->toBe('map-extra-1')
        ->and(Order::query()->value('customer_phone'))->toBe('0612345678');
});

it('imports a YouCan order that stores the phone on the customer instead of extra fields', function () {
    syncCity('Kenitra');
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder([
        'id' => 'native-customer-1',
        'extra_fields' => [
            'Votre Adresse / عنوانك' => 'Centre ville',
        ],
        'customer' => [
            'first_name' => 'Wissame',
            'last_name' => '',
            'phone' => '0660-033231',
            'city' => 'Kenitra',
        ],
    ])]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    commitYouCanReview($this, $seller);

    $order = Order::query()->first();

    expect($order)->not->toBeNull()
        ->and($order->external_order_id)->toBe('native-customer-1')
        ->and($order->customer_phone)->toBe('0660033231')
        ->and($order->customer_first_name)->toBe('Wissame')
        ->and($order->customer_address)->toBe('Centre ville');
});

it('retries YouCan orders that failed on a previous catalog scan', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);
    $integration->update(['last_synced_at' => now()->subMinute()]);

    EcommerceIntegrationSync::query()->create([
        'ecommerce_integration_id' => $integration->id,
        'status' => EcommerceSyncStatus::Failed,
        'trigger' => EcommerceSyncTrigger::Manual,
        'fetched_count' => 35,
        'created_count' => 0,
        'error_count' => 35,
        'started_at' => now()->subMinute(),
        'finished_at' => now()->subMinute(),
    ]);

    fakeYouCanOrders([youCanOrder([
        'id' => 'retry-old-1',
        'created_at' => now()->subMonths(6)->toIso8601String(),
        'customer' => [
            'first_name' => 'Sara',
            'last_name' => 'Benali',
            'phone' => '0612345678',
            'city' => 'Casablanca',
        ],
    ])]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    commitYouCanReview($this, $seller);

    expect(Order::query()->value('external_order_id'))->toBe('retry-old-1');
});

it('does not pull a YouCan order that already failed on a previous sync', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    $failed = youCanOrder([
        'id' => 'still-failed-1',
        'ref' => 'YC-FAILED',
        'created_at' => now()->subMonths(6)->toIso8601String(),
        'extra_fields' => [
            ['name' => 'Nom Complet', 'value' => 'Inconnu'],
            ['name' => 'Téléphone', 'value' => '0611111111'],
            ['name' => 'Ville', 'value' => 'Atlantis'],
            ['name' => 'Adresse', 'value' => 'Somewhere'],
        ],
    ]);

    fakeYouCanOrders([$failed]);

    app(EcommerceOrderSyncService::class)->run($integration, EcommerceSyncTrigger::Schedule);

    expect(EcommerceIntegrationSyncRow::query()->value('status'))->toBe(EcommerceSyncRowStatus::Failed)
        ->and(EcommerceIntegrationSyncRow::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(0);

    fakeYouCanOrders([$failed]);

    $second = app(EcommerceOrderSyncService::class)->run($integration->fresh(), EcommerceSyncTrigger::Schedule);

    expect(EcommerceIntegrationSyncRow::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(0)
        ->and($second->fetched_count)->toBe(0)
        ->and($second->rows()->count())->toBe(0);
});

it('links a YouCan parcel back to the YouCan integration page', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder()]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    commitYouCanReview($this, $seller);

    $order = Order::query()->first();

    $this->actingAs($seller)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('orders/show')
            ->where('order.ecommerce_integration.platform', EcommercePlatform::YouCan->value)
            ->where('order.ecommerce_integration.url', route('integrations.youcan', ['store_id' => $store->id]))
        );
});

it('rescans the catalog after a successful sync that read zero YouCan orders', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);
    $integration->update(['last_synced_at' => now()->subMinute()]);

    EcommerceIntegrationSync::query()->create([
        'ecommerce_integration_id' => $integration->id,
        'status' => EcommerceSyncStatus::Succeeded,
        'trigger' => EcommerceSyncTrigger::Manual,
        'fetched_count' => 0,
        'created_count' => 0,
        'started_at' => now()->subMinute(),
        'finished_at' => now()->subMinute(),
    ]);

    fakeYouCanOrders([youCanOrder([
        'id' => 'caught-up-1',
        'created_at' => now()->subMonths(6)->toIso8601String(),
    ])]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    commitYouCanReview($this, $seller);

    expect(Order::query()->value('external_order_id'))->toBe('caught-up-1');
});

it('skips a YouCan order whose status is not the configured trigger', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder([
        'id' => 'closed-1',
        'status_new' => ['slug' => 'closed'],
    ])]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));

    expect(Order::query()->count())->toBe(0);

    $sync = EcommerceIntegrationSync::query()->first();

    expect($sync->created_count)->toBe(0)
        ->and($sync->skipped_count)->toBe(1)
        ->and($sync->skipped_reasons[0]['reason'])->toBe('status_mismatch');
});

it('does not create the same YouCan order twice', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder()]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    commitYouCanReview($this, $seller);
    $this->actingAs($seller)->post(route('integrations.sync', $integration));

    expect(Order::query()->count())->toBe(1);

    $second = EcommerceIntegrationSync::query()->latest('id')->first();

    expect($second->created_count)->toBe(0)
        ->and($second->skipped_reasons[0]['reason'])->toBe('already_imported');
});

it('skips a YouCan order whose shop reference was already imported', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder()]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    commitYouCanReview($this, $seller);

    fakeYouCanOrders([youCanOrder([
        'id' => 'order-uuid-duplicate-ref',
        'ref' => 'YC-1001',
    ])]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));

    expect(Order::query()->count())->toBe(1);

    $second = EcommerceIntegrationSync::query()->latest('id')->first();

    expect($second->created_count)->toBe(0)
        ->and($second->skipped_count)->toBe(1)
        ->and($second->skipped_reasons[0]['reason'])->toBe('already_imported');
});

it('records a city mismatch as a line error instead of aborting the run', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([
        youCanOrder([
            'id' => 'bad-city',
            'ref' => 'YC-BAD',
            'extra_fields' => [
                ['name' => 'Nom Complet', 'value' => 'Inconnu'],
                ['name' => 'Téléphone', 'value' => '0611111111'],
                ['name' => 'Ville', 'value' => 'Atlantis'],
                ['name' => 'Adresse', 'value' => 'Somewhere'],
            ],
        ]),
        youCanOrder(['id' => 'good-city', 'ref' => 'YC-GOOD']),
    ]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));

    expect(Order::query()->count())->toBe(0);

    $sync = EcommerceIntegrationSync::query()->first();

    expect($sync->status)->toBe(EcommerceSyncStatus::Review)
        ->and($sync->rows()->count())->toBe(2)
        ->and($sync->created_count)->toBe(0);
});

it('saves auto-sync settings and stamps the next run', function () {
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    $this->actingAs($seller)
        ->put(route('integrations.settings.update', $integration), [
            'auto_sync_enabled' => true,
            'sync_interval_minutes' => 30,
            'import_status' => YouCanImportStatus::Paid->value,
        ])
        ->assertRedirect(route('integrations.youcan', ['store_id' => $store->id]));

    $integration->refresh();

    expect($integration->auto_sync_enabled)->toBeTrue()
        ->and($integration->sync_interval_minutes)->toBe(30)
        ->and($integration->import_status)->toBe('paid')
        ->and($integration->next_sync_at)->not->toBeNull();
});

it('filters the order list down to one YouCan sync', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder()]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));
    $sync = commitYouCanReview($this, $seller);

    $this->actingAs($seller)
        ->get(route('orders.index', ['ecommerce_sync_id' => $sync->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('orders/index')
            ->has('orders.data', 1)
            ->where('filters.ecommerce_sync_id', (string) $sync->id)
        );
});

it('runs due auto-syncs from the scheduler command', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);
    $integration->update([
        'auto_sync_enabled' => true,
        'sync_interval_minutes' => 15,
        'next_sync_at' => now()->subMinute(),
    ]);

    fakeYouCanOrders([youCanOrder(['id' => 'scheduled-1'])]);

    $this->artisan('ecommerce:sync-due', ['--sync' => true])
        ->assertSuccessful();

    expect(Order::query()->value('external_order_id'))->toBe('scheduled-1')
        ->and(EcommerceIntegrationSync::query()->first()->trigger)->toBe(EcommerceSyncTrigger::Schedule);
});

it('shows sync history on the YouCan page without leaking the password', function () {
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    EcommerceIntegrationSync::query()->create([
        'ecommerce_integration_id' => $integration->id,
        'status' => EcommerceSyncStatus::Succeeded,
        'trigger' => EcommerceSyncTrigger::Manual,
        'created_count' => 24,
        'started_at' => now()->subMinutes(2),
        'finished_at' => now(),
    ]);

    $this->actingAs($seller)
        ->get(route('integrations.youcan', ['store_id' => $store->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/youcan')
            ->where('integration.latest_sync.created_count', 24)
            ->has('syncs', 1)
            ->has('options.intervals')
            ->missing('integration.client_secret')
            ->missing('integration.access_token')
        );
});

it('persists the YouCan field mapping from settings', function () {
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    $this->actingAs($seller)
        ->put(route('integrations.settings.update', $integration), [
            'auto_sync_enabled' => false,
            'sync_interval_minutes' => 15,
            'import_status' => YouCanImportStatus::Open->value,
            'field_mapping' => [
                'customer_first_name' => 'customer.full_name',
                'customer_phone' => 'customer.phone',
                'city_id' => 'customer.city',
                'customer_address' => 'customer.address',
                'order_amount' => 'total',
            ],
        ])
        ->assertRedirect(route('integrations.youcan', ['store_id' => $store->id]));

    expect($integration->fresh()->field_mapping['customer_first_name'])->toBe('customer.full_name')
        ->and($integration->fresh()->field_mapping['order_amount'])->toBe('total');
});

it('opens the review table after a manual YouCan sync without creating parcels', function () {
    syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([youCanOrder()]);

    $this->actingAs($seller)->post(route('integrations.sync', $integration));

    $sync = EcommerceIntegrationSync::query()->first();

    $this->actingAs($seller)
        ->get(route('integrations.youcan.review', $sync))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/youcan-review')
            ->has('rows', 1)
            ->where('rows.0.customer_first_name', 'Sara')
        );

    expect(Order::query()->count())->toBe(0)
        ->and($sync->status)->toBe(EcommerceSyncStatus::Review);
});

it('lets the seller fix failed auto-sync rows in the review table', function () {
    $city = syncCity();
    $seller = syncSeller();
    $store = syncStore($seller);
    $integration = connectedYouCan($seller, $store);

    fakeYouCanOrders([
        youCanOrder([
            'id' => 'bad-auto',
            'ref' => 'YC-BAD',
            'extra_fields' => [
                ['name' => 'Nom Complet', 'value' => 'Inconnu'],
                ['name' => 'Téléphone', 'value' => '0611111111'],
                ['name' => 'Ville', 'value' => 'Atlantis'],
                ['name' => 'Adresse', 'value' => 'Somewhere'],
            ],
        ]),
        youCanOrder(['id' => 'good-auto', 'ref' => 'YC-GOOD']),
    ]);

    $sync = app(EcommerceOrderSyncService::class)->run($integration, EcommerceSyncTrigger::Schedule);

    expect(Order::query()->value('external_order_id'))->toBe('good-auto')
        ->and($sync->status)->toBe(EcommerceSyncStatus::Partial)
        ->and($sync->created_count)->toBe(1)
        ->and($sync->error_count)->toBe(1);

    $this->actingAs($seller)
        ->get(route('integrations.youcan', ['store_id' => $store->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('integration.review_sync.id', $sync->id)
            ->where('integration.review_sync.reviewable_count', 1)
        );

    $this->actingAs($seller)
        ->get(route('integrations.youcan.review', $sync))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/youcan-review')
            ->has('rows', 1)
            ->where('rows.0.external_order_id', 'bad-auto')
        );

    $row = $sync->rows()->where('status', EcommerceSyncRowStatus::Failed)->first();
    $sector = $city->sectors()->first();
    $values = $row->values ?? [];

    $this->actingAs($seller)
        ->post(route('integrations.youcan.review.store', $sync), [
            'orders' => [[
                'id' => $row->id,
                'customer_first_name' => $values['customer_first_name'] ?: 'Inconnu',
                'customer_last_name' => $values['customer_last_name'] ?: 'Inconnu',
                'customer_phone' => '0611111111',
                'customer_address' => $values['customer_address'] ?: 'Somewhere',
                'city_id' => $city->id,
                'sector_id' => $sector->id,
                'payment_method' => $values['payment_method'] ?? 'CASH',
                'order_amount' => $values['order_amount'] ?? 199,
                'notes' => $values['notes'] ?? null,
                'is_fragile' => false,
                'can_be_opened' => false,
                'option_exchange' => false,
                'delivery_included' => false,
            ]],
        ])
        ->assertRedirect(route('orders.index', ['ecommerce_sync_id' => $sync->id]));

    expect(Order::query()->count())->toBe(2)
        ->and(Order::query()->where('external_order_id', 'bad-auto')->exists())->toBeTrue()
        ->and($sync->refresh()->status)->toBe(EcommerceSyncStatus::Succeeded);
});

it('lets a reader open a connected YouCan page without managing it', function () {
    $owner = syncSeller();
    $store = syncStore($owner);
    connectedYouCan($owner, $store);

    $role = app(TeamRoleService::class)->create($owner, 'Readers', [
        EcommerceIntegrationPermissions::READ,
    ]);
    $member = app(TeamService::class)->create($owner, [
        'first_name' => 'Lina',
        'last_name' => 'R.',
        'email' => 'lina-'.uniqid().'@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    $this->actingAs($member->fresh(['roles.permissions', 'stores']))
        ->get(route('integrations.youcan', ['store_id' => $store->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.manage', false)
        );
});
