<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Features;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Inertia::share([
            'permissions' => fn () => $this->resolvePermissions(),
            'isSuperAdmin' => fn () => (bool) request()->user()?->isSuperAdmin(),
            'auth' => [
                'user' => fn () => $this->resolveAuthUser(),
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function resolvePermissions(): array
    {
        $user = request()->user();

        if (! $user) {
            return [];
        }

        $user->loadMissing('roles.permissions');

        return $user->roles
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAuthUser(): ?array
    {
        $user = request()->user();

        if (! $user) {
            return null;
        }

        $user->loadMissing('roles');

        $roleNames = $user->roles->pluck('name')->values()->all();
        $primaryRole = $roleNames[0] ?? null;

        return array_merge($user->toArray(), [
            'roles' => $roleNames,
            'role_label' => $primaryRole
                ? trans('roles.'.$primaryRole, [], app()->getLocale())
                : null,
            'two_factor_enabled' => Features::enabled(Features::twoFactorAuthentication())
                && ! is_null($user->two_factor_secret),
        ]);
    }
}
