<?php

namespace App\Services\Ecommerce;

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\EcommerceSyncRowStatus;
use App\Enums\ShopifyImportStatus;
use App\Enums\YouCanImportStatus;
use App\Models\EcommerceIntegration;
use App\Models\EcommerceIntegrationSync;
use App\Models\Store;
use App\Models\User;
use App\Services\Ecommerce\Shopify\ShopifyClient;
use App\Services\Ecommerce\Shopify\ShopifyFieldCatalog;
use App\Services\Ecommerce\YouCan\YouCanClient;
use App\Services\Ecommerce\YouCan\YouCanFieldCatalog;
use App\Support\EcommerceIntegrationPermissions;
use App\Support\ShopifyShopDomain;
use App\Support\YouCanShopSlug;
use Illuminate\Validation\ValidationException;
use Throwable;

class EcommerceIntegrationService
{
    public function __construct(
        private readonly YouCanClient $youCan,
        private readonly ShopifyClient $shopify,
    ) {}

    /**
     * Catalogue cards for the vendor's shops, merged with whatever is already
     * connected. Availability is per platform; credentials never leave the server.
     *
     * @param  iterable<int, EcommerceIntegration>  $integrations
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(User $user, iterable $integrations): array
    {
        $byPlatform = collect($integrations)
            ->groupBy(fn (EcommerceIntegration $integration) => $integration->platform->value)
            ->map(function ($group) {
                return $group->first(
                    fn (EcommerceIntegration $integration) => $integration->status === EcommerceIntegrationStatus::Connected
                ) ?? $group->first();
            });

        return array_map(
            function (EcommercePlatform $platform) use ($user, $byPlatform): array {
                $integration = $byPlatform->get($platform->value);

                return [
                    'key' => $platform->value,
                    'name' => $platform->name(),
                    'icon' => $platform->icon(),
                    'color' => $platform->color(),
                    'available' => $platform->isAvailable(),
                    'can_manage' => EcommerceIntegrationPermissions::canConnect($user, $platform),
                    'connection' => $integration instanceof EcommerceIntegration
                        ? $this->present($integration)
                        : null,
                ];
            },
            EcommercePlatform::cases()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function present(EcommerceIntegration $integration): array
    {
        $integration->loadMissing(['store:id,name', 'seller:id,first_name,last_name,email']);

        $latest = $integration->relationLoaded('syncs')
            ? $integration->syncs->sortByDesc('id')->first()
            : $integration->syncs()->latest('id')->first();
        $reviewSync = $integration->relationLoaded('syncs')
            ? ($integration->syncs->first(
                fn (EcommerceIntegrationSync $sync): bool => (int) ($sync->reviewable_count ?? 0) > 0
            ) ?? $integration->latestReviewableSync())
            : $integration->latestReviewableSync();

        return [
            'id' => $integration->id,
            'platform' => $integration->platform->value,
            'status' => $integration->status->value,
            'store_id' => $integration->store_id,
            'store' => $integration->store
                ? ['id' => $integration->store->id, 'name' => $integration->store->name]
                : null,
            'seller' => $this->presentSeller($integration->seller),
            'shop_slug' => $integration->shop_slug,
            'shop_name' => $integration->shop_name,
            'email' => $integration->email,
            'has_password' => $integration->hasPassword(),
            'has_access_token' => $integration->hasAccessToken(),
            'connected_at' => $integration->connected_at?->toIso8601String(),
            'last_error' => $integration->last_error,
            'auto_sync_enabled' => (bool) $integration->auto_sync_enabled,
            'sync_interval_minutes' => (int) $integration->sync_interval_minutes,
            'import_status' => $integration->import_status ?: $this->defaultImportStatus($integration),
            'field_mapping' => $integration->resolvedFieldMapping(),
            'source_fields' => $integration->source_fields ?: $this->builtinSources($integration),
            'last_synced_at' => $integration->last_synced_at?->toIso8601String(),
            'next_sync_at' => $integration->next_sync_at?->toIso8601String(),
            'is_syncing' => $integration->hasRunningSync(),
            'latest_sync' => $latest instanceof EcommerceIntegrationSync
                ? $this->presentSync($latest)
                : null,
            'review_sync' => $reviewSync instanceof EcommerceIntegrationSync
                ? $this->presentSync($reviewSync)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSync(EcommerceIntegrationSync $sync): array
    {
        $status = $sync->status;

        $reviewable = $sync->reviewable_count ?? $sync->rows()
            ->whereIn('status', [
                EcommerceSyncRowStatus::Pending->value,
                EcommerceSyncRowStatus::Failed->value,
            ])
            ->count();

        return [
            'id' => $sync->id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'trigger' => $sync->trigger->value,
            'trigger_label' => $sync->trigger->label(),
            'fetched_count' => (int) $sync->fetched_count,
            'created_count' => (int) $sync->created_count,
            'updated_count' => (int) $sync->updated_count,
            'skipped_count' => (int) $sync->skipped_count,
            'error_count' => (int) $sync->error_count,
            'reviewable_count' => (int) $reviewable,
            'started_at' => $sync->started_at?->toIso8601String(),
            'finished_at' => $sync->finished_at?->toIso8601String(),
            'duration_seconds' => $sync->durationSeconds(),
            'error_message' => $sync->error_message,
            'skipped_reasons' => $sync->skipped_reasons ?? [],
        ];
    }

    /**
     * Compact row for the admin list: no credentials, no per-row sync queries.
     *
     * @return array<string, mixed>
     */
    public function presentSummary(EcommerceIntegration $integration): array
    {
        $integration->loadMissing(['store:id,name', 'seller:id,first_name,last_name,email']);

        return [
            'id' => $integration->id,
            'platform' => $integration->platform->value,
            'platform_name' => $integration->platform->name(),
            'platform_icon' => $integration->platform->icon(),
            'platform_color' => $integration->platform->color(),
            'status' => $integration->status->value,
            'store_id' => $integration->store_id,
            'store' => $integration->store
                ? ['id' => $integration->store->id, 'name' => $integration->store->name]
                : null,
            'seller' => $this->presentSeller($integration->seller),
            'shop_slug' => $integration->shop_slug,
            'shop_name' => $integration->shop_name,
            'email' => $integration->email,
            'connected_at' => $integration->connected_at?->toIso8601String(),
            'last_synced_at' => $integration->last_synced_at?->toIso8601String(),
            'last_error' => $integration->last_error,
            'syncs_count' => (int) ($integration->syncs_count ?? 0),
            'manage_url' => $integration->platform->manageUrl($integration->store_id),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function presentSeller(?User $seller): ?array
    {
        if ($seller === null) {
            return null;
        }

        return [
            'id' => $seller->id,
            'name' => $seller->full_name,
            'email' => $seller->email,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(EcommerceIntegration $integration, array $data): EcommerceIntegration
    {
        $justEnabled = (bool) $data['auto_sync_enabled'] && ! $integration->auto_sync_enabled;

        $integration->auto_sync_enabled = (bool) $data['auto_sync_enabled'];
        $integration->sync_interval_minutes = (int) $data['sync_interval_minutes'];
        $integration->import_status = (string) $data['import_status'];

        if (array_key_exists('field_mapping', $data) && is_array($data['field_mapping'])) {
            $integration->field_mapping = $this->mergeFieldMapping(
                $integration,
                $data['field_mapping'],
            );
        }

        if ($justEnabled) {
            $integration->next_sync_at = now();
        } else {
            $integration->scheduleNextSync();
        }
        $integration->save();

        return $integration->refresh();
    }

    /**
     * Log the seller into YouCan Accounts SSO and bind that shop to the
     * SpeedZone store. YouCan no longer exposes POST /auth/login on
     * api.youcan.shop; the working flow is the same as seller-area login.
     *
     * @param  array<string, mixed>  $data
     */
    public function connectYouCan(array $data, User $actor, Store $store): EcommerceIntegration
    {
        $integration = $this->upsertYouCan($data, $actor, $store);
        $password = filled($data['password'] ?? null)
            ? $data['password']
            : $integration->client_secret;

        if (blank($password) || blank($integration->email)) {
            throw ValidationException::withMessages([
                'password' => __('integrations.youcan.validation.password'),
            ]);
        }

        try {
            $session = $this->youCan->login(
                (string) $integration->email,
                (string) $password,
                $integration->shop_slug,
                filled($data['two_factor_code'] ?? null) ? (string) $data['two_factor_code'] : null,
            );

            $session = $this->scopeSessionToSlug($session, $integration->shop_slug);

            $integration->fill([
                'access_token' => $session['access_token'],
                'token_expires_at' => $session['expired_at'],
                'connected_by' => $actor->id,
                'last_error' => null,
            ])->save();

            $this->verifyYouCanToken($integration);
        } catch (ValidationException $e) {
            $this->markError(
                $integration,
                collect($e->errors())->flatten()->first() ?: $e->getMessage()
            );

            throw $e;
        } catch (Throwable $e) {
            $this->markError($integration, $e->getMessage());

            throw ValidationException::withMessages([
                'email' => $e->getMessage(),
            ]);
        }

        return $integration->refresh();
    }

    /**
     * Verify a custom-app Admin API token against GET /admin/api/{version}/shop.json.
     *
     * @param  array<string, mixed>  $data
     */
    public function connectShopify(array $data, User $actor, Store $store): EcommerceIntegration
    {
        $integration = $this->upsertShopify($data, $actor, $store);
        $token = filled($data['access_token'] ?? null)
            ? (string) $data['access_token']
            : (string) $integration->access_token;
        $domain = (string) $integration->shop_slug;

        if ($token === '' || $domain === '') {
            throw ValidationException::withMessages([
                'access_token' => __('integrations.shopify.validation.access_token'),
            ]);
        }

        try {
            $shop = $this->shopify->shop($domain, $token);

            $integration->fill([
                'status' => EcommerceIntegrationStatus::Connected,
                'access_token' => $token,
                'shop_name' => $shop['name'],
                'shop_slug' => ShopifyShopDomain::normalize($shop['domain']) ?? $domain,
                'external_store_id' => $shop['id'],
                'email' => $shop['email'] ?? $integration->email,
                'connected_by' => $actor->id,
                'connected_at' => now(),
                'token_expires_at' => null,
                'last_error' => null,
            ])->save();
        } catch (ValidationException $e) {
            $this->markError(
                $integration,
                collect($e->errors())->flatten()->first() ?: $e->getMessage()
            );

            throw $e;
        } catch (Throwable $e) {
            $this->markError($integration, $e->getMessage());

            throw ValidationException::withMessages([
                'access_token' => $e->getMessage(),
            ]);
        }

        return $integration->refresh();
    }

    public function disconnect(EcommerceIntegration $integration): void
    {
        $integration->fill([
            'status' => EcommerceIntegrationStatus::Disconnected,
            'client_secret' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'external_store_id' => null,
            'connected_at' => null,
            'last_error' => null,
            'auto_sync_enabled' => false,
            'next_sync_at' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertYouCan(array $data, User $actor, Store $store): EcommerceIntegration
    {
        $integration = EcommerceIntegration::query()->firstOrNew([
            'store_id' => $store->id,
            'platform' => EcommercePlatform::YouCan->value,
        ]);

        $integration->seller_id = (int) $store->owner_id;
        $integration->email = $data['email'];
        $integration->shop_slug = YouCanShopSlug::normalize($data['shop_slug'] ?? null);
        $integration->connected_by = $actor->id;
        $integration->last_error = null;

        if (filled($data['password'] ?? null)) {
            $integration->client_secret = $data['password'];
        }

        if (! $integration->exists) {
            $integration->status = EcommerceIntegrationStatus::Pending;
        } elseif ($integration->status === EcommerceIntegrationStatus::Disconnected) {
            $integration->status = EcommerceIntegrationStatus::Pending;
        }

        $integration->save();

        return $integration;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertShopify(array $data, User $actor, Store $store): EcommerceIntegration
    {
        $integration = EcommerceIntegration::query()->firstOrNew([
            'store_id' => $store->id,
            'platform' => EcommercePlatform::Shopify->value,
        ]);

        $integration->seller_id = (int) $store->owner_id;
        $integration->shop_slug = ShopifyShopDomain::normalize($data['shop_slug'] ?? null);
        $integration->connected_by = $actor->id;
        $integration->last_error = null;

        if (filled($data['access_token'] ?? null)) {
            $integration->access_token = $data['access_token'];
        }

        if (! $integration->exists) {
            $integration->status = EcommerceIntegrationStatus::Pending;
            $integration->import_status = ShopifyImportStatus::Unfulfilled->value;
        } elseif ($integration->status === EcommerceIntegrationStatus::Disconnected) {
            $integration->status = EcommerceIntegrationStatus::Pending;
        }

        $integration->save();

        return $integration;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, string|null>
     */
    private function mergeFieldMapping(EcommerceIntegration $integration, array $mapping): array
    {
        if ($integration->platform === EcommercePlatform::Shopify) {
            return ShopifyFieldCatalog::mergeMapping(
                $mapping,
                ShopifyFieldCatalog::autoMap($integration->source_fields ?: ShopifyFieldCatalog::builtinSources()),
            );
        }

        return YouCanFieldCatalog::mergeMapping(
            $mapping,
            YouCanFieldCatalog::autoMap($integration->source_fields ?: YouCanFieldCatalog::builtinSources()),
        );
    }

    /**
     * @return array<int, array{key: string, label: string, group: string}>
     */
    private function builtinSources(EcommerceIntegration $integration): array
    {
        return $integration->platform === EcommercePlatform::Shopify
            ? ShopifyFieldCatalog::builtinSources()
            : YouCanFieldCatalog::builtinSources();
    }

    private function defaultImportStatus(EcommerceIntegration $integration): string
    {
        return $integration->platform === EcommercePlatform::Shopify
            ? ShopifyImportStatus::Unfulfilled->value
            : YouCanImportStatus::Open->value;
    }

    /**
     * @param  array{access_token: string, expired_at: mixed, stores: array<int, array{store_id: string, slug: string, is_active: bool, name?: string}>}  $session
     * @return array{access_token: string, expired_at: mixed, stores: array<int, array{store_id: string, slug: string, is_active: bool, name?: string}>}
     */
    private function scopeSessionToSlug(array $session, ?string $slug): array
    {
        $stores = $session['stores'];

        if ($stores === []) {
            throw ValidationException::withMessages([
                'email' => __('integrations.youcan.errors.credentials'),
            ]);
        }

        $match = null;

        if (filled($slug)) {
            $needle = strtolower($slug);
            $candidates = collect($stores)->filter(function (array $shop) use ($needle): bool {
                return strtolower($shop['slug']) === $needle
                    || strtolower($shop['name'] ?? '') === $needle;
            })->values();

            if ($candidates->isEmpty()) {
                throw ValidationException::withMessages([
                    'shop_slug' => __('integrations.youcan.errors.unknown_shop', ['slug' => $slug]),
                ]);
            }

            $match = $candidates->first(fn (array $shop): bool => $shop['is_active'])
                ?? $candidates->first();

            if ($match !== null && ! ($match['is_active'] ?? false)) {
                throw ValidationException::withMessages([
                    'shop_slug' => __('integrations.youcan.errors.inactive_shop', ['slug' => $slug]),
                ]);
            }
        } elseif (count($stores) === 1) {
            $match = $stores[0];
        } else {
            $active = collect($stores)->where('is_active', true)->values();

            if ($active->count() === 1) {
                $match = $active[0];
            } else {
                $names = collect($stores)->pluck('slug')->filter()->implode(', ');

                throw ValidationException::withMessages([
                    'shop_slug' => __('integrations.youcan.errors.pick_shop', ['shops' => $names]),
                ]);
            }
        }

        return $this->youCan->switchStore($session['access_token'], $match['store_id']);
    }

    private function verifyYouCanToken(EcommerceIntegration $integration): void
    {
        try {
            $me = $this->youCan->me((string) $integration->access_token);
        } catch (Throwable $e) {
            $this->markError($integration, $e->getMessage());

            throw ValidationException::withMessages([
                'email' => $e->getMessage(),
            ]);
        }

        $slug = YouCanShopSlug::normalize($me['slug'] ?? $me['domain'] ?? null)
            ?? $integration->shop_slug;

        $integration->fill([
            'status' => EcommerceIntegrationStatus::Connected,
            'shop_slug' => $slug,
            'shop_name' => $me['name'] ?? $integration->shop_name,
            'external_store_id' => $me['store_id'] ?? $me['id'] ?? $integration->external_store_id,
            'connected_at' => now(),
            'last_error' => null,
        ])->save();
    }

    private function markError(EcommerceIntegration $integration, string $message): void
    {
        $integration->fill([
            'status' => EcommerceIntegrationStatus::Error,
            'last_error' => $message,
        ])->save();
    }
}
