<?php

namespace App\Providers;

use App\Models\City;
use App\Models\DriverInvoice;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Partner;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sector;
use App\Models\StockReception;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\Transfer;
use App\Models\User;
use App\Policies\CityPolicy;
use App\Policies\DriverInvoicePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrderReturnPolicy;
use App\Policies\PartnerDeliveryPolicy;
use App\Policies\PartnerPolicy;
use App\Policies\PickupRequestPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RolePolicy;
use App\Policies\SectorPolicy;
use App\Policies\StockReceptionPolicy;
use App\Policies\StorePolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\TeamPolicy;
use App\Policies\TransferPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        SupportTicket::class => SupportTicketPolicy::class,
        Partner::class => PartnerPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Store::class => StorePolicy::class,
        Product::class => ProductPolicy::class,
        StockReception::class => StockReceptionPolicy::class,
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

        Gate::define('partner-delivery.view', [PartnerDeliveryPolicy::class, 'view']);
        Gate::define('partner-delivery.update', [PartnerDeliveryPolicy::class, 'update']);
        Gate::define('partner-users.assign', fn (User $user) => $user->hasPermission('partners.update'));

        // Vendor team management: the subject is a User and a Role, both of
        // which already have a policy of their own, so these live as gates.
        Gate::define('team.viewAny', [TeamPolicy::class, 'viewAny']);
        Gate::define('team.create', [TeamPolicy::class, 'create']);
        Gate::define('team.update', [TeamPolicy::class, 'update']);
        Gate::define('team.suspend', [TeamPolicy::class, 'suspend']);
        Gate::define('team-roles.manage', [TeamPolicy::class, 'manageRoles']);
        Gate::define('team-roles.update', [TeamPolicy::class, 'updateRole']);
    }
}
