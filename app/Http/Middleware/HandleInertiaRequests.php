<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
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
        $primaryRole = $roleNames[0] ?? null;

        // withoutRelations() keeps the model's own attributes and accessors but
        // drops loaded relations. Serialising the model as-is drags the eagerly
        // loaded roles.permissions graph into every Inertia response (~31 KB of
        // JSON per request for an admin).
        return array_merge($user->withoutRelations()->toArray(), [
            'roles' => $roleNames,
            'role_label' => $primaryRole
                ? trans('roles.'.$primaryRole, [], app()->getLocale())
                : null,
            'is_seller' => $user->isSeller(),
            'status' => $user->status?->value ?? UserStatus::Active->value,
            'is_account_active' => $user->isAccountActive(),
            'is_pending_approval' => $user->isPendingApproval(),
            'can_view_returns' => $user->canAccessReturnsModule(),
            'can_create_return_request' => $user->canCreateReturnRequest(),
            'two_factor_enabled' => Features::enabled(Features::twoFactorAuthentication())
                && ! is_null($user->two_factor_secret),
        ]);
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
