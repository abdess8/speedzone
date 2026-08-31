<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\Store;
use App\Services\AlertService;
use App\Services\Chatbot\ChatbotService;
use App\Support\StoreContext;
use App\Support\TranslationBundle;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    /**
     * Header used by the client to advertise the locale bundle it already holds.
     */
    public const LOCALE_HEADER = 'X-Inertia-Locale';

    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        $shared = array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // A batch action that partly succeeded is neither, and saying
                // "success" over three failed parcels would be a lie.
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'locale' => $locale,
            'permissions' => fn () => $request->user()?->permissionNames() ?? [],
            'isSuperAdmin' => fn () => (bool) $request->user()?->isSuperAdmin(),
            // Overrides Jetstream's ShareInertiaData, which serialises the whole
            // user model including every loaded relation.
            'auth' => [
                'user' => fn () => $this->resolveAuthUser($request),
            ],
            'notifications' => [
                'unread_count' => fn () => (int) ($request->user()?->unreadNotifications()->count() ?? 0),
            ],
            'store' => fn () => $this->resolveStoreContext($request),
            // Lets the floating assistant stay out of the DOM entirely when the
            // feature is off or no API key is configured, rather than offering
            // a launcher whose every message would fail.
            'chatbot' => fn () => [
                'enabled' => app(ChatbotService::class)->isEnabled(),
            ],
            // Not `alerts`: the management screen already ships a prop by that
            // name, and a page prop shadows a shared one.
            'announcements' => fn () => $this->resolveAlerts($request),
        ]);

        // The Vue i18n instance keeps merged messages for the lifetime of the
        // SPA, so the ~64 KB bundle only needs to travel on the initial document
        // request (or when the locale changed). Omitting it from subsequent
        // Inertia XHR visits removes that payload from every navigation.
        if ($this->clientNeedsTranslations($request, $locale)) {
            $shared['translations'] = fn () => TranslationBundle::forLocale($locale);
        }

        return $shared;
    }

    /**
     * The authenticated user as consumed by the frontend.
     *
     * @return array<string, mixed>|null
     */
    private function resolveAuthUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $roleNames = $user->roleNames();

        // withoutRelations() keeps the model's own attributes and accessors but
        // drops loaded relations. Serialising the model as-is drags the eagerly
        // loaded roles.permissions graph into every Inertia response (~31 KB of
        // JSON per request for an admin).
        return array_merge($user->withoutRelations()->toArray(), [
            'roles' => $roleNames,
            'role_label' => $user->primaryRoleLabel(),
            'is_seller' => $user->isSeller(),
            'status' => $user->status?->value ?? UserStatus::Active->value,
            'is_account_active' => $user->isAccountActive(),
            'is_pending_approval' => $user->isPendingApproval(),
            'can_view_returns' => $user->canAccessReturnsModule(),
            'can_create_return_request' => $user->canCreateReturnRequest(),
            // Only vendors are scored: the missing fields are all seller
            // paperwork, so the gauge would be meaningless for staff accounts.
            'profile_completion' => $user->isSeller() ? $user->profileCompletion() : null,
            'two_factor_enabled' => Features::enabled(Features::twoFactorAuthentication())
                && ! is_null($user->two_factor_secret),
        ]);
    }

    /**
     * Announcements on display for the current user, split into the banners
     * that ride at the top of the page and the modals that open over it.
     *
     * @return array{banners: array<int, array<string, mixed>>, modals: array<int, array<string, mixed>>}
     */
    private function resolveAlerts(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return ['banners' => [], 'modals' => []];
        }

        return app(AlertService::class)->forUser($user);
    }

    /**
     * The multi-store context consumed by the switcher and the login picker.
     *
     * Null for staff accounts and for vendors who only have one shop, so the
     * switcher simply does not render for them.
     *
     * @return array<string, mixed>|null
     */
    private function resolveStoreContext(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || ! $user->belongsToStoreAccount()) {
            return null;
        }

        $stores = $user->stores()
            ->where('stores.is_active', true)
            ->orderByDesc('stores.is_default')
            ->orderBy('stores.name')
            ->get(['stores.id', 'stores.name', 'stores.logo_path', 'stores.category']);

        if ($stores->isEmpty()) {
            return null;
        }

        $activeId = app(StoreContext::class)->id() ?? $user->defaultStoreId();
        $active = $stores->firstWhere('id', $activeId);

        return [
            'active' => $active ? $this->storePayload($active) : null,
            'available' => $stores->map(fn (Store $store) => $this->storePayload($store))->all(),
            // Only ask which shop to open when there is actually a choice to
            // make and the user has not made it yet this session.
            'must_choose' => $stores->count() > 1
                && ! $request->session()->get(ResolveActiveStore::CHOSEN_KEY, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storePayload(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'category' => $store->category,
            'logo_url' => $store->logo_url,
        ];
    }

    /**
     * Whether the translation bundle has to be sent with this response.
     *
     * Fails safe: whenever the client does not tell us what it already has,
     * the bundle is sent.
     */
    private function clientNeedsTranslations(Request $request, string $locale): bool
    {
        if (! $request->inertia()) {
            return true;
        }

        return $request->header(self::LOCALE_HEADER) !== $locale;
    }
}
