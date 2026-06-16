<?php

namespace App\Providers;

use App\Models\City;
use App\Models\DriverInvoice;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PickupRequest;
use App\Models\Sector;
use App\Models\Transfer;
use App\Models\User;
use App\Policies\CityPolicy;
use App\Policies\DriverInvoicePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrderReturnPolicy;
use App\Policies\PickupRequestPolicy;
use App\Policies\SectorPolicy;
use App\Policies\TransferPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
        City::class => CityPolicy::class,
        Sector::class => SectorPolicy::class,
        PickupRequest::class => PickupRequestPolicy::class,
        Transfer::class => TransferPolicy::class,
        OrderReturn::class => OrderReturnPolicy::class,
        Invoice::class => InvoicePolicy::class,
        DriverInvoice::class => DriverInvoicePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Super admins bypass every authorization check.
        Gate::before(function (User $user) {
            return $user->isSuperAdmin() ? true : null;
        });

        // Driver zone management is permission-based (no single model owner).
        Gate::define('driver_zones.read', fn (User $user) => $user->hasPermission('driver_zones.read'));
        Gate::define('driver_zones.assign', fn (User $user) => $user->hasPermission('driver_zones.assign'));
        Gate::define('driver_zones.remove', fn (User $user) => $user->hasPermission('driver_zones.remove'));
    }
}
