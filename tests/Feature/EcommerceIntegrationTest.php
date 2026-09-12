<?php

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Models\EcommerceIntegration;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
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

function integrationSeller(): User
{
    return StockFixtures::user(Role::SELLER);
}

function integrationStore(User $owner, string $name = 'Atlas Concept'): Store
{
    return StockFixtures::store($owner, $name);
}

/**
 * @param  array<int, string>  $permissions
 */
function integrationMember(User $owner, Store $store, array $permissions): User
{
    $role = app(TeamRoleService::class)->create($owner, 'Integrations', $permissions);

    $member = app(TeamService::class)->create($owner, [
        'first_name' => 'Nadia',
        'last_name' => 'K.',
        'email' => 'nadia-'.uniqid().'@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    return $member->fresh(['roles.permissions', 'stores']);
}

function youCanPayload(Store $store, array $overrides = []): array
{
    return array_merge([
        'store_id' => $store->id,
        'shop_slug' => 'https://atlas.youcan.shop',
        'email' => 'seller@youcan.test',
        'password' => 'youcan-password',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $options
 */
function fakeYouCanSession(array $options = []): void
{
    $stores = $options['stores'] ?? [[
        'id' => 'store-uuid-1',
        'slug' => 'atlas',
        'name' => 'Atlas Concept',
        'active' => true,
    ]];

    Http::fake(function (Request $request) use ($options, $stores) {
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
            $status = $options['login_status'] ?? 200;

            if ($status !== 200) {
                return Http::response([
                    'errors' => [
                        'username' => [$options['login_error'] ?? 'Invalid credentials'],
                    ],
                ], $status);
            }

            if ($options['require_2fa'] ?? false) {
                return Http::response('2fa', 302, [
                    'Location' => 'https://accounts.youcan.shop/account/2fa/verification',
                ]);
            }

            return Http::response('ok', 302, [
                'Location' => 'https://accounts.youcan.shop/redirect?to=https://seller-area.youcan.shop',
            ]);
        }

        if (str_contains($url, '/account/2fa/verification')) {
            return Http::response('ok', 302, [
                'Location' => 'https://accounts.youcan.shop/redirect?to=https://seller-area.youcan.shop',
            ]);
        }

        if (str_contains($url, '/sso/login')) {
            return Http::response('<html>login</html>', 200);
        }

        if (str_contains($url, '/shop/stores')) {
            return Http::response(['data' => $stores], 200);
        }

        if (preg_match('#/admin/([^/]+)/switch-store#', $url, $matches)) {
            return Http::response('switched', 200);
        }

        if (str_contains($url, 'get-dotshop-object')) {
            $meStatus = $options['me_status'] ?? 200;

            if ($meStatus !== 200) {
                return Http::response(['message' => 'Unauthenticated'], $meStatus);
            }

            return Http::response([
                'store' => array_merge([
                    'id' => 'store-uuid-1',
                    'slug' => 'atlas',
                    'name' => 'Atlas Concept',
                ], $options['me'] ?? []),
                'owner' => [
                    'email' => 'seller@youcan.test',
                ],
            ], 200);
        }

        if (str_contains($url, 'seller-area.youcan.shop/admin')) {
            return Http::response('ok', 200);
        }

        return Http::response(['detail' => 'unexpected '.$url], 500);
    });
}

it('lets a seller open the catalogue with YouCan available and the others still coming', function () {
    $seller = integrationSeller();
    integrationStore($seller);

    $this->actingAs($seller)
        ->get(route('integrations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/index')
            ->has('platforms', 4)
            ->where('platforms.0.key', 'youcan')
            ->where('platforms.0.available', true)
            ->where('platforms.0.can_manage', true)
            ->where('platforms.0.connection', null)
            ->where('platforms.1.available', false)
        );
});

it('keeps drivers out of the catalogue', function () {
    $driver = StockFixtures::user(Role::DRIVER);

    $this->actingAs($driver)
        ->get(route('integrations.index'))
        ->assertForbidden();
});

it('connects YouCan with the seller email and password from the Store Admin API', function () {
    fakeYouCanSession();

    $seller = integrationSeller();
    $store = integrationStore($seller);

    $this->actingAs($seller)
        ->from(route('integrations.youcan'))
        ->post(route('integrations.youcan.store'), youCanPayload($store))
        ->assertRedirect(route('integrations.youcan', ['store_id' => $store->id]))
        ->assertSessionHas('success');

    $integration = EcommerceIntegration::query()->first();

    expect($integration)->not->toBeNull()
        ->and($integration->seller_id)->toBe($seller->id)
        ->and($integration->store_id)->toBe($store->id)
        ->and($integration->platform)->toBe(EcommercePlatform::YouCan)
        ->and($integration->shop_slug)->toBe('atlas')
        ->and($integration->email)->toBe('seller@youcan.test')
        ->and($integration->client_secret)->toBe('youcan-password')
        ->and($integration->access_token)->toBe('sso:store-uuid-1')
        ->and($integration->shop_name)->toBe('Atlas Concept')
        ->and($integration->external_store_id)->toBe('store-uuid-1')
        ->and($integration->status)->toBe(EcommerceIntegrationStatus::Connected)
        ->and($integration->token_expires_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/sso/login')
        && $request->method() === 'POST'
        && $request['username'] === 'seller@youcan.test'
        && $request['password'] === 'youcan-password');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/admin/store-uuid-1/switch-store'));
});

it('never sends YouCan secrets to the browser', function () {
    $seller = integrationSeller();
    $store = integrationStore($seller);

    EcommerceIntegration::query()->create([
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'platform' => EcommercePlatform::YouCan,
        'status' => EcommerceIntegrationStatus::Connected,
        'shop_slug' => 'atlas',
        'email' => 'seller@youcan.test',
        'client_secret' => 'youcan-password',
        'access_token' => 'yc_bearer_token',
        'connected_at' => now(),
        'connected_by' => $seller->id,
    ]);

    $this->actingAs($seller)
        ->get(route('integrations.youcan', ['store_id' => $store->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/youcan')
            ->where('integration.email', 'seller@youcan.test')
            ->where('integration.has_password', true)
            ->where('integration.has_access_token', true)
            ->missing('integration.client_secret')
            ->missing('integration.access_token')
            ->missing('integration.refresh_token')
            ->missing('integration.password')
        );
});

it('sends the 2FA code when YouCan asks for it', function () {
    fakeYouCanSession(['require_2fa' => true]);

    $seller = integrationSeller();
    $store = integrationStore($seller);

    $this->actingAs($seller)
        ->post(route('integrations.youcan.store'), youCanPayload($store, [
            'two_factor_code' => '407468',
        ]))
        ->assertRedirect(route('integrations.youcan', ['store_id' => $store->id]));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/account/2fa/verification')
        && $request['code'] === '407468');
});

it('scopes the session onto the chosen shop when the account has several YouCan shops', function () {
    fakeYouCanSession([
        'stores' => [
            ['id' => 'store-uuid-1', 'slug' => 'atlas', 'name' => 'Atlas', 'active' => true],
            ['id' => 'store-uuid-2', 'slug' => 'hightech', 'name' => 'Hightech', 'active' => true],
        ],
        'me' => [
            'id' => 'store-uuid-2',
            'slug' => 'hightech',
            'name' => 'Hightech',
        ],
    ]);

    $seller = integrationSeller();
    $store = integrationStore($seller);

    $this->actingAs($seller)
        ->post(route('integrations.youcan.store'), youCanPayload($store, [
            'shop_slug' => 'hightech',
        ]))
        ->assertRedirect(route('integrations.youcan', ['store_id' => $store->id]));

    $integration = EcommerceIntegration::query()->first();

    expect($integration->access_token)->toBe('sso:store-uuid-2')
        ->and($integration->shop_slug)->toBe('hightech')
        ->and($integration->external_store_id)->toBe('store-uuid-2');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/admin/store-uuid-2/switch-store'));
});

it('prefers the active shop when a slug matches an inactive store and an active name', function () {
    fakeYouCanSession([
        'stores' => [
            ['id' => 'inactive-id', 'slug' => 'soleana', 'name' => 'Old Soleana', 'active' => false],
            ['id' => 'active-id', 'slug' => 'smartwear', 'name' => 'Soleana', 'active' => true],
        ],
        'me' => [
            'id' => 'active-id',
            'slug' => 'smartwear',
            'name' => 'Soleana',
        ],
    ]);

    $seller = integrationSeller();
    $store = integrationStore($seller);

    $this->actingAs($seller)
        ->post(route('integrations.youcan.store'), youCanPayload($store, [
            'shop_slug' => 'soleana',
        ]))
        ->assertRedirect(route('integrations.youcan', ['store_id' => $store->id]));

    $integration = EcommerceIntegration::query()->first();

    expect($integration->external_store_id)->toBe('active-id')
        ->and($integration->shop_slug)->toBe('smartwear')
        ->and($integration->shop_name)->toBe('Soleana');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/admin/active-id/switch-store'));
});

it('refuses to connect an inactive YouCan shop', function () {
    fakeYouCanSession([
        'stores' => [
            ['id' => 'inactive-id', 'slug' => 'soleana', 'name' => 'Soleana Old', 'active' => false],
            ['id' => 'other-id', 'slug' => 'smartwears', 'name' => 'SmartWear', 'active' => true],
        ],
    ]);

    $seller = integrationSeller();
    $store = integrationStore($seller);

    $this->actingAs($seller)
        ->from(route('integrations.youcan'))
        ->post(route('integrations.youcan.store'), youCanPayload($store, [
            'shop_slug' => 'soleana',
        ]))
        ->assertRedirect(route('integrations.youcan'))
        ->assertSessionHasErrors('shop_slug');
});

it('does not mark YouCan as connected when the seller-area JSON API refuses the session', function () {
    fakeYouCanSession(['me_status' => 401]);

    $seller = integrationSeller();
    $store = integrationStore($seller);

    $this->actingAs($seller)
        ->from(route('integrations.youcan'))
        ->post(route('integrations.youcan.store'), youCanPayload($store))
        ->assertRedirect(route('integrations.youcan'))
        ->assertSessionHasErrors('email');

    expect(EcommerceIntegration::query()->first()->status)->toBe(EcommerceIntegrationStatus::Error);
});

it('rejects a YouCan slug that is not on the seller account', function () {
    fakeYouCanSession();

    $seller = integrationSeller();
    $store = integrationStore($seller);

    $this->actingAs($seller)
        ->from(route('integrations.youcan'))
        ->post(route('integrations.youcan.store'), youCanPayload($store, [
            'shop_slug' => 'missing-shop',
        ]))
        ->assertRedirect(route('integrations.youcan'))
        ->assertSessionHasErrors('shop_slug');

    expect(EcommerceIntegration::query()->value('status'))->toBe(EcommerceIntegrationStatus::Error);
});

it('keeps the stored password when the seller reconnects without typing it again', function () {
    fakeYouCanSession();

    $seller = integrationSeller();
    $store = integrationStore($seller);

    EcommerceIntegration::query()->create([
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'platform' => EcommercePlatform::YouCan,
        'status' => EcommerceIntegrationStatus::Error,
        'shop_slug' => 'atlas',
        'email' => 'seller@youcan.test',
        'client_secret' => 'youcan-password',
        'connected_by' => $seller->id,
    ]);

    $this->actingAs($seller)
        ->post(route('integrations.youcan.store'), youCanPayload($store, [
            'password' => '',
        ]))
        ->assertRedirect(route('integrations.youcan', ['store_id' => $store->id]));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/sso/login')
        && $request->method() === 'POST'
        && $request['password'] === 'youcan-password');
});

it('surfaces a YouCan login failure on the form', function () {
    fakeYouCanSession([
        'login_status' => 422,
        'login_error' => 'Invalid credentials',
    ]);

    $seller = integrationSeller();
    $store = integrationStore($seller);

    $this->actingAs($seller)
        ->from(route('integrations.youcan'))
        ->post(route('integrations.youcan.store'), youCanPayload($store))
        ->assertRedirect(route('integrations.youcan'))
        ->assertSessionHasErrors('email');

    expect(EcommerceIntegration::query()->value('status'))->toBe(EcommerceIntegrationStatus::Error)
        ->and(EcommerceIntegration::query()->value('last_error'))->toBe('Invalid credentials');
});

it('redirects leftover OAuth callbacks back to the login form', function () {
    $seller = integrationSeller();
    integrationStore($seller);

    $this->actingAs($seller)
        ->get(route('integrations.youcan.callback', [
            'code' => 'auth-code-1',
            'state' => 'stale',
        ]))
        ->assertRedirect(route('integrations.youcan'))
        ->assertSessionHas('error');
});

it('lets a team member holding only the YouCan grant connect that platform', function () {
    fakeYouCanSession();

    $owner = integrationSeller();
    $store = integrationStore($owner);
    $member = integrationMember($owner, $store, [
        EcommerceIntegrationPermissions::READ,
        EcommerceIntegrationPermissions::manage(EcommercePlatform::YouCan),
    ]);

    expect($member->hasPermission(EcommerceIntegrationPermissions::MANAGE))->toBeFalse()
        ->and($member->hasPermission(EcommerceIntegrationPermissions::manage(EcommercePlatform::YouCan)))->toBeTrue();

    $this->actingAs($member)
        ->from(route('integrations.youcan'))
        ->post(route('integrations.youcan.store'), youCanPayload($store))
        ->assertRedirect(route('integrations.youcan', ['store_id' => $store->id]));

    expect(EcommerceIntegration::query()->value('seller_id'))->toBe($owner->id);
});

it('stops a team member with only the Shopify grant from connecting YouCan', function () {
    $owner = integrationSeller();
    $store = integrationStore($owner);
    $member = integrationMember($owner, $store, [
        EcommerceIntegrationPermissions::READ,
        EcommerceIntegrationPermissions::manage(EcommercePlatform::Shopify),
    ]);

    $this->actingAs($member)
        ->post(route('integrations.youcan.store'), youCanPayload($store))
        ->assertForbidden();

    expect(EcommerceIntegration::query()->count())->toBe(0);
});

it('lets a reader see the catalogue without opening the YouCan form', function () {
    $owner = integrationSeller();
    $store = integrationStore($owner);
    $member = integrationMember($owner, $store, [EcommerceIntegrationPermissions::READ]);

    $this->actingAs($member)
        ->get(route('integrations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('platforms.0.can_manage', false)
        );

    $this->actingAs($member)
        ->get(route('integrations.youcan'))
        ->assertForbidden();
});

it('refuses to hang a YouCan shop on someone else\'s store', function () {
    $seller = integrationSeller();
    $other = integrationSeller();
    $foreign = integrationStore($other, 'Someone else');

    $this->actingAs($seller)
        ->post(route('integrations.youcan.store'), youCanPayload($foreign))
        ->assertForbidden();
});

it('wipes credentials when YouCan is disconnected', function () {
    $seller = integrationSeller();
    $store = integrationStore($seller);

    $integration = EcommerceIntegration::query()->create([
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'platform' => EcommercePlatform::YouCan,
        'status' => EcommerceIntegrationStatus::Connected,
        'shop_slug' => 'atlas',
        'email' => 'seller@youcan.test',
        'client_secret' => 'youcan-password',
        'access_token' => 'yc_bearer_token',
        'connected_at' => now(),
        'connected_by' => $seller->id,
    ]);

    $this->actingAs($seller)
        ->delete(route('integrations.destroy', $integration))
        ->assertRedirect(route('integrations.index', ['platform' => 'youcan']));

    $integration->refresh();

    expect($integration->status)->toBe(EcommerceIntegrationStatus::Disconnected)
        ->and($integration->client_secret)->toBeNull()
        ->and($integration->access_token)->toBeNull()
        ->and($integration->email)->toBe('seller@youcan.test');
});

it('gives the seller every storefront grant so he can delegate them one by one', function () {
    $seller = integrationSeller();

    foreach (EcommerceIntegrationPermissions::sellerDefaults() as $permission) {
        expect($seller->hasPermission($permission))->toBeTrue();
    }
});
