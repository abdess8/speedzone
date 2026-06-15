<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

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
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => fn () => app()->getLocale(),
            'translations' => fn () => [
                'sidebar' => trans('sidebar'),
                'navbar' => trans('navbar'),
                'roles' => trans('roles'),
                'common' => trans('common'),
                'orders' => trans('orders'),
                'pickups' => trans('pickups'),
                'transfers' => trans('transfers'),
                'returns' => trans('returns'),
                'users' => trans('users'),
                'cities' => trans('cities'),
                'sectors' => trans('sectors'),
                'driver_zones' => trans('driver_zones'),
                'profile' => trans('profile'),
            ],
        ]);
    }
}
