<?php

namespace App\Providers;

use App\Actions\Jetstream\DeleteUser;
use App\Http\Controllers\Profile\ProfileScreenController;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The package route resolves its controller through the container, so
        // the profile screen can be extended without touching its URL or name.
        $this->app->bind(UserProfileController::class, ProfileScreenController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Configure the permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::permissions([
            'create',
            'read',
            'update',
            'delete',
        ]);
    }
}
