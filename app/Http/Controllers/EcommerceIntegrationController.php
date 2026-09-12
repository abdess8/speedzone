<?php

namespace App\Http\Controllers;

use App\Enums\EcommercePlatform;
use App\Enums\EcommerceSyncRowStatus;
use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use App\Enums\PaymentMethod;
use App\Enums\YouCanImportStatus;
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
use App\Support\EcommerceIntegrationPermissions;
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
 * YouCan is the first connector. The seller signs in with the same email and
 * password as seller-area.youcan.shop (`POST /auth/login` on the Store Admin API).
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
        $user = $request->user();
        $canManage = EcommerceIntegrationPermissions::canConnect($user, EcommercePlatform::YouCan);
        $canView = $canManage || $this->canReadIntegrations($user);

        if (! $canView) {
            abort(403);
        }

        $stores = $this->accessibleStores($user);
        $storeId = $request->integer('store_id') ?: app(StoreContext::class)->id() ?: $stores->first()?->id;

        if ($request->filled('store_id') && ! $user->isSuperAdmin() && ! $user->canAccessStore((int) $storeId)) {
            abort(403);
        }

        $integration = $storeId
            ? EcommerceIntegration::query()
                ->where('store_id', $storeId)
                ->where('platform', EcommercePlatform::YouCan->value)
                ->with('store:id,name')
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

        return Inertia::render('integrations/youcan', [
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
                'import_statuses' => YouCanImportStatus::options(),
            ],
            'can' => [
                'manage' => $canManage,
            ],
            'defaults' => [
                'store_id' => $storeId,
            ],
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
            ->route('integrations.youcan', ['store_id' => $integration->store_id])
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
                ->route('integrations.youcan', ['store_id' => $integration->store_id])
                ->with('error', __('integrations.sync.already_running'));
        }

        $inline = config('queue.default') === 'sync';

        if ($inline) {
            $sync = $this->orderSyncs->run($integration, EcommerceSyncTrigger::Manual);

            if ($sync->status === EcommerceSyncStatus::Review) {
                return redirect()
                    ->route('integrations.youcan.review', $sync)
                    ->with('success', __('integrations.sync.review_ready', [
                        'count' => $sync->rows()->where('status', EcommerceSyncRowStatus::Pending->value)->count(),
                    ]));
            }

            $message = $sync->status->value === 'failed'
                ? ($sync->error_message ?: __('integrations.sync.failed'))
                : __('integrations.sync.completed', ['count' => $sync->created_count]);

            return redirect()
                ->route('integrations.youcan', ['store_id' => $integration->store_id])
                ->with($sync->status->value === 'failed' ? 'error' : 'success', $message);
        }

        dispatch(new SyncEcommerceOrdersJob($integration->id, EcommerceSyncTrigger::Manual));

        return redirect()
            ->route('integrations.youcan', ['store_id' => $integration->store_id])
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

        return Inertia::render('integrations/youcan-review', [
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

    private function canReadIntegrations(User $user): bool
    {
        return $user->hasPermission(EcommerceIntegrationPermissions::READ)
            || $user->hasPermission(EcommerceIntegrationPermissions::MANAGE);
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
