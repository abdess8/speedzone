<?php

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\ShopifyImportStatus;
use App\Models\EcommerceIntegration;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
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

function shopifySeller(): User
{
    return StockFixtures::user(Role::SELLER);
}

function shopifyStore(User $owner, string $name = 'Atlas Concept'): Store
{
    return StockFixtures::store($owner, $name);
}

/**
 * @param  array<string, mixed>  $options
 */
function fakeShopifyAdmin(array $options = []): void
{
    Http::fake(function (Request $request) use ($options) {
        $url = $request->url();
        $token = $request->header('X-Shopify-Access-Token')[0] ?? null;

        if ($token !== ($options['token'] ?? 'shpat_test_token')) {
            return Http::response(['errors' => '[API] Invalid API key or access token'], 401);
        }

        if (str_contains($url, '/shop.json')) {
            $status = $options['shop_status'] ?? 200;

            if ($status !== 200) {
                return Http::response(['errors' => $options['shop_error'] ?? 'Not Found'], $status);
            }

            return Http::response([
                'shop' => array_merge([
                    'id' => 998877,
                    'name' => 'Atlas Shopify',
                    'myshopify_domain' => 'atlas.myshopify.com',
                    'email' => 'owner@atlas.test',
                ], $options['shop'] ?? []),
            ]);
        }

        return Http::response(['errors' => 'unexpected '.$url], 500);
    });
}

function shopifyPayload(Store $store, array $overrides = []): array
{
    return array_merge([
        'store_id' => $store->id,
        'shop_slug' => 'https://atlas.myshopify.com/admin',
        'access_token' => 'shpat_test_token',
    ], $overrides);
}

it('connects Shopify with a custom-app Admin API token', function () {
    fakeShopifyAdmin();

    $seller = shopifySeller();
    $store = shopifyStore($seller);

    $this->actingAs($seller)
        ->from(route('integrations.shopify'))
        ->post(route('integrations.shopify.store'), shopifyPayload($store))
        ->assertRedirect(route('integrations.shopify', ['store_id' => $store->id]))
        ->assertSessionHas('success');

    $integration = EcommerceIntegration::query()->first();

    expect($integration)->not->toBeNull()
        ->and($integration->seller_id)->toBe($seller->id)
        ->and($integration->store_id)->toBe($store->id)
        ->and($integration->platform)->toBe(EcommercePlatform::Shopify)
        ->and($integration->shop_slug)->toBe('atlas.myshopify.com')
        ->and($integration->shop_name)->toBe('Atlas Shopify')
        ->and($integration->external_store_id)->toBe('998877')
        ->and($integration->email)->toBe('owner@atlas.test')
        ->and($integration->access_token)->toBe('shpat_test_token')
        ->and($integration->status)->toBe(EcommerceIntegrationStatus::Connected)
        ->and($integration->import_status)->toBe(ShopifyImportStatus::Unfulfilled->value)
        ->and($integration->connected_by)->toBe($seller->id);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'atlas.myshopify.com/admin/api/2026-07/shop.json')
        && $request->header('X-Shopify-Access-Token') === ['shpat_test_token']);
});

it('never sends the Shopify access token to the browser', function () {
    $seller = shopifySeller();
    $store = shopifyStore($seller);

    EcommerceIntegration::query()->create([
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'platform' => EcommercePlatform::Shopify,
        'status' => EcommerceIntegrationStatus::Connected,
        'shop_slug' => 'atlas.myshopify.com',
        'shop_name' => 'Atlas Shopify',
        'access_token' => 'shpat_secret',
        'connected_at' => now(),
        'connected_by' => $seller->id,
        'import_status' => ShopifyImportStatus::Unfulfilled->value,
    ]);

    $this->actingAs($seller)
        ->get(route('integrations.shopify', ['store_id' => $store->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/shopify')
            ->where('integration.shop_slug', 'atlas.myshopify.com')
            ->where('integration.has_access_token', true)
            ->missing('integration.access_token')
            ->missing('integration.client_secret')
        );
});

it('lets an admin connect Shopify on a seller\'s shop without attaching it to the admin', function () {
    fakeShopifyAdmin();

    $admin = StockFixtures::user(Role::ADMIN);
    $seller = shopifySeller();
    $store = shopifyStore($seller);

    $this->actingAs($admin)
        ->from(route('integrations.shopify', ['seller_id' => $seller->id]))
        ->post(route('integrations.shopify.store'), shopifyPayload($store))
        ->assertRedirect(route('integrations.shopify', ['store_id' => $store->id]))
        ->assertSessionHas('success');

    $integration = EcommerceIntegration::query()->first();

    expect($integration)->not->toBeNull()
        ->and($integration->seller_id)->toBe($seller->id)
        ->and($integration->store_id)->toBe($store->id)
        ->and($integration->connected_by)->toBe($admin->id)
        ->and($integration->platform)->toBe(EcommercePlatform::Shopify)
        ->and($integration->status)->toBe(EcommerceIntegrationStatus::Connected);

    expect(EcommerceIntegration::query()->where('seller_id', $admin->id)->exists())->toBeFalse();
});

it('rejects an invalid Shopify Admin API token', function () {
    fakeShopifyAdmin(['shop_status' => 401]);

    $seller = shopifySeller();
    $store = shopifyStore($seller);

    $this->actingAs($seller)
        ->from(route('integrations.shopify'))
        ->post(route('integrations.shopify.store'), shopifyPayload($store))
        ->assertRedirect(route('integrations.shopify'))
        ->assertSessionHasErrors('access_token');

    expect(EcommerceIntegration::query()->value('status'))->toBe(EcommerceIntegrationStatus::Error);
});
