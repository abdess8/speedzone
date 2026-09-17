<?php

namespace App\Http\Controllers;

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\EcommerceSyncRowStatus;
use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use App\Enums\PaymentMethod;
use App\Enums\ShopifyImportStatus;
use App\Enums\YouCanImportStatus;
use App\Http\Requests\ConnectShopifyRequest;
use App\Http\Requests\ConnectYouCanRequest;
use App\Http\Requests\ImportYouCanReviewRequest;
use App\Http\Requests\UpdateYouCanSettingsRequest;
use App\Jobs\SyncEcommerceOrdersJob;
use App\Models\City;
use App\Models\EcommerceIntegration;
use App\Models\EcommerceIntegrationSync;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use App\Services\Ecommerce\EcommerceIntegrationService;
use App\Services\Ecommerce\EcommerceOrderSyncService;
use App\Support\AdminSellerDirectory;
use App\Support\EcommerceIntegrationPermissions;
use App\Support\SortableQuery;
use App\Support\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Storefront connectors a vendor activates on his own shops.
 *
 * Each platform is opted into independently: the catalogue is the chooser,
 * YouCan and Shopify are the live connectors. YouCan uses seller-area SSO;
 * Shopify uses a custom-app Admin API token against the REST Admin API.
 */
class EcommerceIntegrationController extends Controller
{
    public function __construct(
        private readonly EcommerceIntegrationService $integrations,
        private readonly EcommerceOrderSyncService $orderSyncs,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EcommerceIntegration::class);

        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return $this->adminIndex($request);
        }

        $stores = $this->accessibleStores($user);
        $storeIds = $stores->pluck('id')->all();
        $focusId = app(StoreContext::class)->id();
        $focusIds = $focusId && in_array($focusId, $storeIds, true) ? [$focusId] : $storeIds;

        $connections = $focusIds === []
            ? collect()
            : EcommerceIntegration::query()
                ->whereIn('store_id', $focusIds)
                ->with('store:id,name')
                ->get();

        return Inertia::render('integrations/index', [
            'platforms' => $this->integrations->catalogue($user, $connections),
            'stores' => $stores->map(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
            ])->values()->all(),
            'can' => [
                'manage' => $user->hasPermission(EcommerceIntegrationPermissions::MANAGE),
                'platforms' => $this->platformAbilities($user),
            ],
            'selected' => $request->string('platform')->toString() ?: null,
        ]);
    }

    public function youcan(Request $request): Response
    {
        return $this->connectorPage($request, EcommercePlatform::YouCan, 'integrations/youcan', [
            'import_statuses' => YouCanImportStatus::options(),
        ]);
    }

    public function shopify(Request $request): Response
    {
        return $this->connectorPage($request, EcommercePlatform::Shopify, 'integrations/shopify', [
            'import_statuses' => ShopifyImportStatus::options(),
        ]);
    }

    public function storeYouCan(ConnectYouCanRequest $request): RedirectResponse
    {
        $store = $request->store();
        $this->authorize('create', [EcommerceIntegration::class, EcommercePlatform::YouCan, $store]);

        $this->integrations->connectYouCan($request->validated(), $request->user(), $store);

        return redirect()
            ->route('integrations.youcan', ['store_id' => $store->id])
            ->with('success', __('integrations.youcan.connected'));
    }

    public function storeShopify(ConnectShopifyRequest $request): RedirectResponse
    {
        $store = $request->store();
        $this->authorize('create', [EcommerceIntegration::class, EcommercePlatform::Shopify, $store]);

        $this->integrations->connectShopify($request->validated(), $request->user(), $store);

        return redirect()
            ->route('integrations.shopify', ['store_id' => $store->id])
            ->with('success', __('integrations.shopify.connected'));
    }

    public function callbackYouCan(): RedirectResponse
    {
        return redirect()
            ->route('integrations.youcan')
            ->with('error', __('integrations.youcan.errors.oauth'));
    }

    public function updateYouCanSettings(
        UpdateYouCanSettingsRequest $request,
        EcommerceIntegration $integration,
    ): RedirectResponse {
        $this->authorize('update', $integration);

        $this->integrations->updateSettings($integration, $request->validated());

        return redirect()
            ->to($integration->platform->manageUrl($integration->store_id))
            ->with('success', __('integrations.sync.settings_saved'));
    }

    public function syncYouCan(EcommerceIntegration $integration): RedirectResponse
    {
        $this->authorize('update', $integration);

        if (! $integration->isConnected()) {
            throw ValidationException::withMessages([
                'sync' => __('integrations.sync.errors.not_connected'),
            ]);
        }

        if ($integration->hasRunningSync()) {
            return redirect()
                ->to($integration->platform->manageUrl($integration->store_id))
                ->with('error', __('integrations.sync.already_running'));
        }

        $inline = config('queue.default') === 'sync';

        if ($inline) {
            $sync = $this->orderSyncs->run($integration, EcommerceSyncTrigger::Manual);

            if ($sync->status === EcommerceSyncStatus::Review) {
                return redirect()
                    ->route($this->reviewRouteName($integration), $sync)
                    ->with('success', __('integrations.sync.review_ready', [
                        'count' => $sync->rows()->where('status', EcommerceSyncRowStatus::Pending->value)->count(),
                    ]));
            }

            $message = $sync->status->value === 'failed'
                ? ($sync->error_message ?: __('integrations.sync.failed'))
                : __('integrations.sync.completed', ['count' => $sync->created_count]);

            return redirect()
                ->to($integration->platform->manageUrl($integration->store_id))
                ->with($sync->status->value === 'failed' ? 'error' : 'success', $message);
        }

        dispatch(new SyncEcommerceOrdersJob($integration->id, EcommerceSyncTrigger::Manual));

        return redirect()
            ->to($integration->platform->manageUrl($integration->store_id))
            ->with('success', __('integrations.sync.queued'));
    }

    public function reviewYouCan(EcommerceIntegrationSync $sync): Response
    {
        $sync->load('integration');
        $this->authorize('update', $sync->integration);

        $rows = $sync->rows()
            ->whereIn('status', [
                EcommerceSyncRowStatus::Pending->value,
                EcommerceSyncRowStatus::Failed->value,
            ])
            ->orderBy('id')
            ->get();

        return Inertia::render($this->reviewView($sync->integration), [
            'sync' => $this->integrations->presentSync($sync),
            'integration' => $this->integrations->present($sync->integration),
            'rows' => $rows->map(function ($row) {
                $values = $row->values ?? [];

                return array_merge($values, [
                    'id' => $row->id,
                    '_id' => $row->id,
                    '_line' => $row->ref ?: $row->external_order_id,
                    '_raw' => $row->raw ?? [],
                    '_errors' => $this->reviewFieldErrors($row->errors),
                    'external_order_id' => $row->external_order_id,
                    'status' => $row->status->value,
                ]);
            })->values()->all(),
            'cities' => City::options(),
            'sectors' => Sector::query()
                ->active()
                ->whereHas('city', fn ($query) => $query->active())
                ->orderBy('name')
                ->get(['id', 'city_id', 'name', 'delivery_price'])
                ->map(fn (Sector $sector) => [
                    'id' => $sector->id,
                    'city_id' => $sector->city_id,
                    'name' => $sector->name,
                    'delivery_price' => (float) $sector->delivery_price,
                ])
                ->all(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function commitYouCanReview(
        ImportYouCanReviewRequest $request,
        EcommerceIntegrationSync $sync,
    ): RedirectResponse {
        $sync->load('integration');
        $this->authorize('update', $sync->integration);

        $created = $this->orderSyncs->commit($sync, $request->rows(), $request->user());

        return redirect()
            ->route('orders.index', ['ecommerce_sync_id' => $sync->id])
            ->with('success', __('integrations.sync.completed', ['count' => $created]));
    }

    public function destroy(EcommerceIntegration $integration): RedirectResponse
    {
        $this->authorize('delete', $integration);

        $platform = $integration->platform;
        $this->integrations->disconnect($integration);

        return redirect()
            ->route('integrations.index', ['platform' => $platform->value])
            ->with('success', __('integrations.disconnected', ['platform' => $platform->name()]));
    }

    /**
     * Shared YouCan / Shopify connector screen: same seller picker, store
     * picker, history and settings, different credentials form.
     *
     * @param  array<string, mixed>  $options
     */
    private function connectorPage(
        Request $request,
        EcommercePlatform $platform,
        string $view,
        array $options,
    ): Response {
        $user = $request->user();
        $canManage = EcommerceIntegrationPermissions::canConnect($user, $platform);
        $canView = $canManage || $this->canReadIntegrations($user);
        $admin = $user->isSuperAdmin();

        if (! $canView) {
            abort(403);
        }

        $sellers = $admin ? $this->adminSellers() : collect();
        $stores = $admin
            ? $this->adminStores($request, $sellers)
            : $this->accessibleStores($user);

        $storeId = $request->integer('store_id')
            ?: ($admin ? null : (app(StoreContext::class)->id() ?: $stores->first()?->id));

        if ($admin && ! $request->filled('store_id') && $request->filled('seller_id')) {
            $storeId = $stores->first()?->id;
        }

        if ($request->filled('store_id') && ! $admin && ! $user->canAccessStore((int) $storeId)) {
            abort(403);
        }

        if ($admin && $request->filled('store_id') && $storeId && ! $stores->contains('id', $storeId)) {
            abort(404);
        }

        $integration = $storeId
            ? EcommerceIntegration::query()
                ->where('store_id', $storeId)
                ->where('platform', $platform->value)
                ->with(['store:id,name', 'seller:id,first_name,last_name,email'])
                ->first()
            : null;

        if ($integration) {
            $this->authorize('view', $integration);
        } elseif (! $canManage) {
            abort(403);
        }

        $syncs = $integration
            ? $integration->syncs()
                ->withCount([
                    'rows as reviewable_count' => fn ($query) => $query->whereIn('status', [
                        EcommerceSyncRowStatus::Pending->value,
                        EcommerceSyncRowStatus::Failed->value,
                    ]),
                ])
                ->latest('id')
                ->limit(50)
                ->get()
            : collect();

        if ($integration) {
            $integration->setRelation('syncs', $syncs);
        }

        $sellerId = $stores->first()?->owner_id
            ?: ($request->integer('seller_id') ?: null)
            ?: $integration?->seller_id;

        return Inertia::render($view, [
            'admin' => $admin,
            'sellers' => $sellers->map(fn (User $seller) => [
                'id' => $seller->id,
                'name' => $seller->full_name,
                'email' => $seller->email,
            ])->values()->all(),
            'stores' => $stores->map(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
            ])->values()->all(),
            'integration' => $integration ? $this->integrations->present($integration) : null,
            'syncs' => $syncs->map(fn ($sync) => $this->integrations->presentSync($sync))->values()->all(),
            'options' => [
                'intervals' => array_map(
                    fn (int $minutes) => [
                        'value' => $minutes,
                        'label' => __('integrations.sync.intervals.'.$minutes),
                    ],
                    EcommerceIntegration::SYNC_INTERVALS
                ),
                ...$options,
            ],
            'can' => [
                'manage' => $canManage,
            ],
            'defaults' => [
                'store_id' => $storeId,
                'seller_id' => $sellerId ?: null,
            ],
        ]);
    }

    private function reviewView(EcommerceIntegration $integration): string
    {
        return $integration->platform === EcommercePlatform::Shopify
            ? 'integrations/shopify-review'
            : 'integrations/youcan-review';
    }

    private function reviewRouteName(EcommerceIntegration $integration): string
    {
        return $integration->platform === EcommercePlatform::Shopify
            ? 'integrations.shopify.review'
            : 'integrations.youcan.review';
    }

    private function canReadIntegrations(User $user): bool
    {
        return $user->hasPermission(EcommerceIntegrationPermissions::READ)
            || $user->hasPermission(EcommerceIntegrationPermissions::MANAGE);
    }

    private function adminIndex(Request $request): Response
    {
        $search = (string) $request->string('search');
        $sortable = [
            'seller' => 'seller_id',
            'platform' => 'platform',
            'status' => 'status',
            'connected_at' => 'connected_at',
            'last_synced_at' => 'last_synced_at',
        ];

        $query = EcommerceIntegration::query()
            ->with(['store:id,name', 'seller:id,first_name,last_name,email'])
            ->withCount('syncs')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('shop_slug', 'like', '%'.$search.'%')
                        ->orWhere('shop_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhereHas('seller', function ($seller) use ($search) {
                            $seller->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('platform'), fn ($query) => $query->where(
                'platform',
                $request->string('platform')->toString()
            ))
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ));

        SortableQuery::apply($query, $request, $sortable, 'connected_at');

        $integrations = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (EcommerceIntegration $integration) => $this->integrations->presentSummary($integration));

        return Inertia::render('integrations/admin', [
            'integrations' => $integrations,
            'stats' => [
                'total' => EcommerceIntegration::query()->count(),
                'connected' => EcommerceIntegration::query()
                    ->where('status', EcommerceIntegrationStatus::Connected)
                    ->count(),
                'error' => EcommerceIntegration::query()
                    ->where('status', EcommerceIntegrationStatus::Error)
                    ->count(),
            ],
            'platforms' => array_map(
                fn (EcommercePlatform $platform) => [
                    'value' => $platform->value,
                    'label' => $platform->name(),
                ],
                EcommercePlatform::cases()
            ),
            'statuses' => array_map(
                fn (EcommerceIntegrationStatus $status) => [
                    'value' => $status->value,
                    'label' => __('integrations.status.'.$status->value),
                ],
                EcommerceIntegrationStatus::cases()
            ),
            'filters' => array_merge(
                $request->only(['search', 'platform', 'status']),
                SortableQuery::state($request, $sortable, 'connected_at')
            ),
            'can' => [
                'manage' => true,
            ],
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function adminSellers()
    {
        return AdminSellerDirectory::query()
            ->whereHas('ownedStores')
            ->get(['id', 'first_name', 'last_name', 'email']);
    }

    /**
     * @param  Collection<int, User>  $sellers
     * @return Collection<int, Store>
     */
    private function adminStores(Request $request, $sellers)
    {
        $sellerId = $request->integer('seller_id');
        $storeId = $request->integer('store_id');

        if ($storeId > 0) {
            $store = Store::query()->find($storeId);

            if ($store === null) {
                return collect();
            }

            $sellerId = (int) $store->owner_id;
        }

        if ($sellerId < 1) {
            return collect();
        }

        if ($sellers->isNotEmpty() && ! $sellers->contains('id', $sellerId)) {
            return collect();
        }

        return Store::query()
            ->where('owner_id', $sellerId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'owner_id']);
    }

    /**
     * @return array<string, bool>
     */
    private function platformAbilities(User $user): array
    {
        $abilities = [];

        foreach (EcommercePlatform::cases() as $platform) {
            $abilities[$platform->value] = EcommerceIntegrationPermissions::canConnect($user, $platform);
        }

        return $abilities;
    }

    /**
     * @return Collection<int, Store>
     */
    private function accessibleStores(User $user)
    {
        if (! $user->belongsToStoreAccount()) {
            return collect();
        }

        $ids = $user->accessibleStoreIds();

        if ($ids === []) {
            return collect();
        }

        return Store::query()
            ->whereIn('id', $ids)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @return array<string, string>
     */
    private function reviewFieldErrors(?array $errors): array
    {
        if ($errors === null || $errors === []) {
            return [];
        }

        $map = [
            'invalid_phone' => 'customer_phone',
            'missing_customer' => 'customer_first_name',
            'missing_address' => 'customer_address',
            'unknown_city' => 'city_id',
            'no_sector' => 'sector_id',
            'create_failed' => 'notes',
        ];

        $mapped = [];

        foreach ($errors as $reason => $detail) {
            $field = $map[$reason] ?? null;

            if ($field === null) {
                continue;
            }

            $mapped[$field] = is_string($detail) && $detail !== ''
                ? $detail
                : (string) $reason;
        }

        return $mapped;
    }
}
