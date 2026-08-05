<?php

use App\Http\Controllers\ActiveStoreController;
use App\Http\Controllers\Admin\PendingUserController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertDismissalController;
use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DriverFinanceController;
use App\Http\Controllers\DriverInvoiceController;
use App\Http\Controllers\DriverTransactionController;
use App\Http\Controllers\DriverZoneController;
use App\Http\Controllers\GuideAccessController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderImportController;
use App\Http\Controllers\OrderPreparationController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PartnerDeliveryController;
use App\Http\Controllers\PartnerOrderController;
use App\Http\Controllers\PartnerUserAssignmentController;
use App\Http\Controllers\PendingApprovalController;
use App\Http\Controllers\PickupRequestController;
use App\Http\Controllers\ProductBlockController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\StockInventoryController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockReceptionController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TeamRoleController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VelzonRoutesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*
|--------------------------------------------------------------------------
| Public marketing site (SpeedZone landing) — registered first so it wins
| over the authenticated "/" dashboard route below. Fully independent from
| the dashboard; guests and authenticated users can both reach it.
|--------------------------------------------------------------------------
*/
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/tracking/{trackingNumber}', [LandingController::class, 'track'])
    ->where('trackingNumber', '[A-Za-z0-9\-]+')
    ->name('tracking.public');

Route::get('/verify-email', fn () => redirect()->route('verification.notice'))->name('verify-email');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/account/pending-approval', [PendingApprovalController::class, 'show'])
        ->name('account.pending-approval');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'account.active'])->group(function () {

    Route::get('/dashboard/seller', [SellerDashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard.seller');

    Route::prefix('admin')->name('admin.')->middleware('permission:users.read')->group(function () {
        Route::get('pending-users', [PendingUserController::class, 'index'])->name('pending-users.index');
        Route::get('pending-users/{user}', [PendingUserController::class, 'show'])->name('pending-users.show');
        Route::post('users/{user}/approve', [PendingUserController::class, 'approve'])->name('users.approve');
        Route::post('users/{user}/reject', [PendingUserController::class, 'reject'])->name('users.reject');
    });

    Route::redirect('/admin/users/pending', '/admin/pending-users');

    Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'show'])->name('notification-preferences.show');
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

    // User management
    Route::resource('users', UserController::class)
        ->middleware('permission:users.read');

    // Role & permission management. The guide grid is declared first so
    // `roles/guides` is not swallowed by the resource's `roles/{role}` routes.
    Route::middleware('permission:roles.read')->group(function () {
        Route::get('roles/guides', [GuideAccessController::class, 'edit'])->name('roles.guides.edit');
        Route::put('roles/guides', [GuideAccessController::class, 'update'])->name('roles.guides.update');
    });

    Route::resource('roles', RoleController::class)
        ->except(['show'])
        ->middleware('permission:roles.read');

    // Vendor shops (multi-store). The active-store switch is intentionally
    // outside the stores.read guard: a team member may switch between the shops
    // he was granted without being allowed to administer them.
    Route::put('stores/active', [ActiveStoreController::class, 'update'])
        ->name('stores.active.update');
    Route::resource('stores', StoreController::class)
        ->except(['show'])
        ->whereNumber('store')
        ->middleware('permission:stores.read');

    // Vendor team. Custom roles are declared before the member resource so
    // `team/roles` is not swallowed by `team/{member}`.
    Route::middleware('permission:team_roles.manage')->group(function () {
        Route::resource('team/roles', TeamRoleController::class)
            ->except(['show'])
            ->whereNumber('role')
            ->names('team.roles')
            ->parameters(['roles' => 'role']);
    });

    Route::middleware('permission:team.read')->group(function () {
        Route::put('team/{member}/suspend', [TeamMemberController::class, 'suspend'])
            ->whereNumber('member')
            ->name('team.suspend');
        Route::put('team/{member}/reactivate', [TeamMemberController::class, 'reactivate'])
            ->whereNumber('member')
            ->name('team.reactivate');
        // No destroy: a member who created orders must stay referenceable, so
        // revoking access means suspending him.
        Route::resource('team', TeamMemberController::class)
            ->except(['show', 'destroy'])
            ->whereNumber('member')
            ->parameters(['team' => 'member']);
    });

    // Delivery zones — Cities & Sectors management
    Route::get('cities/{city}/sectors', [CityController::class, 'sectors'])
        ->whereNumber('city')
        ->middleware('permission:cities.read')
        ->name('cities.sectors');
    Route::resource('cities', CityController::class)
        ->whereNumber('city')
        ->middleware('permission:cities.read');
    Route::resource('sectors', SectorController::class)
        ->whereNumber('sector')
        ->middleware('permission:sectors.read');

    // Announcements pushed to users as a banner or a sign-in modal
    Route::get('alerts/users', [AlertController::class, 'searchUsers'])
        ->middleware('permission:alerts.read')
        ->name('alerts.users');
    Route::patch('alerts/{alert}/toggle', [AlertController::class, 'toggle'])
        ->whereNumber('alert')
        ->middleware('permission:alerts.update')
        ->name('alerts.toggle');
    Route::resource('alerts', AlertController::class)
        ->except('show')
        ->whereNumber('alert')
        ->middleware('permission:alerts.read');

    // Dismissal belongs to the recipient, so it sits outside the admin gate.
    Route::post('alerts/{alert}/dismiss', AlertDismissalController::class)
        ->whereNumber('alert')
        ->name('alerts.dismiss');

    // B2B partner integrations
    Route::post('partners/test-connection', [PartnerController::class, 'testConnectionDraft'])
        ->name('partners.test-connection.draft');
    Route::post('partners/{partner}/test-connection', [PartnerController::class, 'testConnectionPreview'])
        ->whereNumber('partner')
        ->name('partners.test-connection');
    Route::post('partners/{partner}/sync', [PartnerController::class, 'sync'])
        ->whereNumber('partner')
        ->name('partners.sync');
    Route::resource('partners', PartnerController::class)
        ->whereNumber('partner')
        ->middleware('permission:partners.read');

    Route::get('partner-assignments', [PartnerUserAssignmentController::class, 'index'])
        ->middleware('permission:partners.update')
        ->name('partner-assignments.index');
    Route::post('partner-assignments/{partner}/users', [PartnerUserAssignmentController::class, 'assign'])
        ->whereNumber('partner')
        ->name('partner-assignments.assign');
    Route::delete('partner-assignments/{partner}/users/{user}', [PartnerUserAssignmentController::class, 'remove'])
        ->whereNumber('partner')
        ->whereNumber('user')
        ->name('partner-assignments.remove');

    Route::get('partner-orders', [PartnerOrderController::class, 'index'])->name('partner-orders.index');
    Route::post('partner-orders/scan', [PartnerOrderController::class, 'scan'])->name('partner-orders.scan');
    Route::post('partner-orders/bulk-advance-status', [PartnerOrderController::class, 'bulkAdvanceStatus'])
        ->name('partner-orders.bulk-advance-status');
    Route::post('partner-orders/bulk-assign-driver', [PartnerOrderController::class, 'bulkAssignDriver'])
        ->name('partner-orders.bulk-assign-driver');
    Route::post('partner-orders/bulk-scan', [PartnerOrderController::class, 'bulkScan'])
        ->name('partner-orders.bulk-scan');

    Route::patch('partner-deliveries/{order}/status', [PartnerDeliveryController::class, 'updateStatus'])
        ->whereNumber('order')
        ->name('partner-deliveries.update-status');

    // Driver zone assignment
    Route::get('driver-zones', [DriverZoneController::class, 'index'])
        ->middleware('permission:driver_zones.read')
        ->name('driver-zones.index');
    Route::post('driver-zones/{driver}/sectors', [DriverZoneController::class, 'assign'])
        ->whereNumber('driver')->name('driver-zones.assign');
    Route::delete('driver-zones/{driver}/sectors/{sector}', [DriverZoneController::class, 'remove'])
        ->whereNumber('driver')->whereNumber('sector')->name('driver-zones.remove');

    // Order management (logistics).
    //
    // The permission middleware here duplicates the controller's authorize()
    // calls on purpose: a route that answers 403 before booting the controller
    // is the same rule the sidebar uses to hide the entry, so "not in the menu"
    // and "forbidden by URL" can never drift apart.
    Route::get('orders/export', [OrderController::class, 'export'])
        ->middleware('permission:orders.export')
        ->name('orders.export');
    Route::get('orders/labels', [OrderController::class, 'labels'])
        ->middleware('permission:orders.print')
        ->name('orders.labels');
    Route::post('orders/bulk-status', [OrderController::class, 'bulkStatus'])
        ->middleware('permission:orders.update.all|orders.update.own|orders.update.assigned')
        ->name('orders.bulk-status');
    Route::post('orders/create-and-new', [OrderController::class, 'storeAndNew'])
        ->middleware('permission:orders.create')
        ->name('orders.store-and-new');
    Route::get('orders/import', [OrderImportController::class, 'create'])
        ->middleware('permission:orders.create')
        ->name('orders.import');
    Route::post('orders/import', [OrderImportController::class, 'store'])
        ->middleware('permission:orders.create')
        ->name('orders.import.store');
    // Picking bench for stock orders. Grouped rather than annotated one by one:
    // the four routes are a single workstation and must never drift apart.
    Route::middleware('permission:orders.transition.to_prepared')->group(function () {
        Route::get('orders/preparation', [OrderPreparationController::class, 'index'])
            ->name('orders.preparation.index');
        Route::post('orders/preparation', [OrderPreparationController::class, 'prepare'])
            ->name('orders.preparation.prepare');
        Route::post('orders/preparation/scan', [OrderPreparationController::class, 'scan'])
            ->name('orders.preparation.scan');
        Route::post('orders/preparation/bulk-scan', [OrderPreparationController::class, 'bulkScan'])
            ->name('orders.preparation.bulk-scan');
    });

    Route::get('orders/{order}/pdf', [OrderController::class, 'pdf'])
        ->whereNumber('order')
        ->middleware('permission:orders.print')
        ->name('orders.pdf');
    Route::post('orders/{order}/assign-driver', [OrderController::class, 'assignDriver'])
        ->whereNumber('order')
        ->name('orders.assign-driver');
    Route::post('orders/{order}/sync-partner', [OrderController::class, 'syncPartner'])
        ->whereNumber('order')
        ->name('orders.sync-partner');
    Route::resource('orders', OrderController::class)
        ->whereNumber('order')
        ->middleware('permission:orders.read.all|orders.read.own|orders.read.assigned');
    // QR code target: /orders/SPD-2026-583920 → public tracking timeline.
    Route::get('orders/{trackingNumber}', [OrderController::class, 'track'])
        ->where('trackingNumber', '[A-Za-z0-9]+-[0-9]{4}-[0-9]+')
        ->name('orders.track');

    // Vendor fulfilment: catalog, inventory, inbound shipments.
    //
    // Like the order routes above, the permission middleware duplicates the
    // controller's authorize() calls so that "hidden in the sidebar" and "403 on
    // direct URL" stay two views of the same rule. Custom paths are registered
    // before the resources so `products/import` is not swallowed by
    // `products/{product}`.
    Route::get('products/import', [ProductImportController::class, 'create'])
        ->middleware('permission:stock.create_product')
        ->name('products.import');
    Route::post('products/import', [ProductImportController::class, 'store'])
        ->middleware('permission:stock.create_product')
        ->name('products.import.store');
    Route::put('products/{product}/block', ProductBlockController::class)
        ->whereNumber('product')
        ->middleware('permission:stock.admin_override')
        ->name('products.block');
    Route::resource('products', ProductController::class)
        ->whereNumber('product')
        ->middleware('permission:stock.view|stock.receive_inbound|stock.admin_override');

    // Mass inventory. Reading the sheet only needs stock.view; writing a
    // correction is guarded per product by ProductPolicy::adjust().
    Route::get('stock/inventory', [StockInventoryController::class, 'index'])
        ->middleware('permission:stock.view|stock.admin_override')
        ->name('stock.inventory');
    Route::post('stock/inventory', [StockInventoryController::class, 'store'])
        ->middleware('permission:stock.adjust|stock.admin_override')
        ->name('stock.inventory.store');

    // Cross-vendor movement audit.
    Route::get('stock/movements', [StockMovementController::class, 'index'])
        ->middleware('permission:stock.admin_override')
        ->name('stock.movements');

    Route::put('stock-receptions/{reception}/send', [StockReceptionController::class, 'send'])
        ->whereNumber('reception')
        ->middleware('permission:stock.create_inbound')
        ->name('stock-receptions.send');
    Route::put('stock-receptions/{reception}/collect', [StockReceptionController::class, 'collect'])
        ->whereNumber('reception')
        ->middleware('permission:stock.collect_inbound|stock.admin_override')
        ->name('stock-receptions.collect');
    Route::put('stock-receptions/{reception}/dispatch', [StockReceptionController::class, 'dispatchToDepot'])
        ->whereNumber('reception')
        ->middleware('permission:stock.collect_inbound|stock.receive_inbound|stock.admin_override')
        ->name('stock-receptions.dispatch');
    Route::put('stock-receptions/{reception}/validate', [StockReceptionController::class, 'validateReception'])
        ->whereNumber('reception')
        ->middleware('permission:stock.receive_inbound')
        ->name('stock-receptions.validate');
    Route::put('stock-receptions/{reception}/cancel', [StockReceptionController::class, 'cancel'])
        ->whereNumber('reception')
        ->middleware('permission:stock.create_inbound|stock.collect_inbound|stock.receive_inbound')
        ->name('stock-receptions.cancel');
    Route::resource('stock-receptions', StockReceptionController::class)
        ->except(['destroy'])
        ->whereNumber('reception')
        ->parameters(['stock-receptions' => 'reception'])
        ->middleware('permission:stock.view|stock.create_inbound|stock.collect_inbound|stock.receive_inbound|stock.admin_override');

    // Pickup requests (Ramassages)
    Route::post('pickup/scan', [PickupRequestController::class, 'scan'])
        ->name('pickup.scan');
    Route::post('pickup/bulk-status-update', [PickupRequestController::class, 'bulkStatusUpdate'])
        ->name('pickup.bulk-status-update');
    Route::post('pickup-requests/bulk-scan', [PickupRequestController::class, 'bulkScan'])
        ->name('pickup-requests.bulk-scan');
    Route::get('pickup-requests/{pickupRequest}/pdf', [PickupRequestController::class, 'pdf'])
        ->whereNumber('pickupRequest')
        ->name('pickup-requests.pdf');
    Route::post('pickup-requests/{pickupRequest}/assign-driver', [PickupRequestController::class, 'assignDriver'])
        ->whereNumber('pickupRequest')
        ->name('pickup-requests.assign-driver');
    Route::post('pickup-requests/{pickupRequest}/change-status', [PickupRequestController::class, 'changeStatus'])
        ->whereNumber('pickupRequest')
        ->name('pickup-requests.change-status');
    Route::resource('pickup-requests', PickupRequestController::class)
        ->only(['index', 'store', 'show'])
        ->whereNumber('pickupRequest')
        ->middleware('permission:pickup_requests.read.all|pickup_requests.read.own|pickup_requests.read.assigned');

    // Inter-city transfers — QR scan URL must be registered before numeric {transfer} routes
    Route::get('transfers/eligible-orders', [TransferController::class, 'eligibleOrders'])
        ->name('transfers.eligible-orders');
    Route::get('transfers/eligible-returns', [TransferController::class, 'eligibleReturns'])
        ->name('transfers.eligible-returns');
    Route::get('transfers/{reference}', [TransferController::class, 'track'])
        ->where('reference', 'TRF-[0-9]{4}-[0-9]+')
        ->name('transfers.track');
    Route::post('transfers/{transfer}/assign-staff', [TransferController::class, 'assignStaff'])
        ->whereNumber('transfer')
        ->name('transfers.assign-staff');
    Route::post('transfers/{transfer}/dispatch', [TransferController::class, 'dispatch'])
        ->whereNumber('transfer')
        ->name('transfers.dispatch');
    Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])
        ->whereNumber('transfer')
        ->name('transfers.receive');
    Route::post('transfers/{transfer}/change-status', [TransferController::class, 'changeStatus'])
        ->whereNumber('transfer')
        ->name('transfers.change-status');
    Route::post('transfers/{transfer}/scan', [TransferController::class, 'scan'])
        ->whereNumber('transfer')
        ->name('transfers.scan');
    Route::post('transfers/{transfer}/bulk-receive', [TransferController::class, 'bulkReceive'])
        ->whereNumber('transfer')
        ->name('transfers.bulk-receive');
    Route::get('transfers/{transfer}/qr', [TransferController::class, 'qr'])
        ->whereNumber('transfer')
        ->name('transfers.qr');
    Route::resource('transfers', TransferController::class)
        ->only(['index', 'store', 'show', 'update'])
        ->whereNumber('transfer')
        ->middleware('permission:transfers.read|transfers.read.assigned');

    // Returns (reverse logistics)
    Route::get('returns/eligible-orders', [ReturnController::class, 'eligibleOrders'])
        ->name('returns.eligible-orders');
    Route::get('returns/{reference}', [ReturnController::class, 'track'])
        ->where('reference', 'RTN-[0-9]{4}-[0-9]+')
        ->name('returns.track');
    Route::post('returns/scan', [ReturnController::class, 'scan'])
        ->name('returns.scan');
    Route::post('returns/process-scan', [ReturnController::class, 'processScan'])
        ->name('returns.process-scan');
    Route::post('returns/{return}/change-status', [ReturnController::class, 'changeStatus'])
        ->whereNumber('return')
        ->name('returns.change-status');
    Route::post('returns/{return}/receive-at-hub', [ReturnController::class, 'receiveAtHub'])
        ->whereNumber('return')
        ->name('returns.receive-at-hub');
    Route::put('returns/{return}/customer-data', [ReturnController::class, 'updateCustomerData'])
        ->whereNumber('return')
        ->name('returns.update-customer-data');
    Route::get('returns/{return}/qr', [ReturnController::class, 'qr'])
        ->whereNumber('return')
        ->name('returns.qr');
    Route::resource('returns', ReturnController::class)
        ->only(['index', 'store', 'show'])
        ->whereNumber('return')
        ->middleware('permission:returns.read.all|returns.read.own|returns.create_request|returns.create|returns.update_status|returns.manage');

    // Invoicing / seller billing — custom routes registered before the resource
    Route::get('invoices/pending', [InvoiceController::class, 'pending'])->name('invoices.pending');
    Route::post('invoices/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->whereNumber('invoice')->name('invoices.pdf');
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])
        ->whereNumber('invoice')->name('invoices.pay');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])
        ->whereNumber('invoice')->name('invoices.send');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
        ->whereNumber('invoice')->name('invoices.cancel');
    Route::resource('invoices', InvoiceController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy'])
        ->whereNumber('invoice')
        ->middleware('permission:invoices.read.all|invoices.read.own');

    // Driver finance & billing — custom routes registered before the resource
    Route::get('driver-finance', [DriverFinanceController::class, 'dashboard'])
        ->middleware('permission:driver_invoices.read.own|driver_invoices.read.all')
        ->name('driver-finance.dashboard');
    // Manual ledger entries (bonus / penalty / adjustment) for a driver
    Route::post('driver-transactions', [DriverTransactionController::class, 'store'])
        ->middleware('permission:driver_invoices.adjust')
        ->name('driver-transactions.store');
    Route::delete('driver-transactions/{driverTransaction}', [DriverTransactionController::class, 'destroy'])
        ->whereNumber('driverTransaction')
        ->middleware('permission:driver_invoices.adjust')
        ->name('driver-transactions.destroy');
    Route::get('driver-invoices/pending', [DriverInvoiceController::class, 'pending'])->name('driver-invoices.pending');
    Route::get('driver-invoices/payments', [DriverInvoiceController::class, 'payments'])->name('driver-invoices.payments');
    Route::post('driver-invoices/preview', [DriverInvoiceController::class, 'preview'])->name('driver-invoices.preview');
    Route::get('driver-invoices/{driverInvoice}/pdf', [DriverInvoiceController::class, 'pdf'])
        ->whereNumber('driverInvoice')->name('driver-invoices.pdf');
    Route::post('driver-invoices/{driverInvoice}/pay', [DriverInvoiceController::class, 'pay'])
        ->whereNumber('driverInvoice')->name('driver-invoices.pay');
    Route::post('driver-invoices/{driverInvoice}/cancel', [DriverInvoiceController::class, 'cancel'])
        ->whereNumber('driverInvoice')->name('driver-invoices.cancel');
    Route::resource('driver-invoices', DriverInvoiceController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy'])
        ->whereNumber('driverInvoice')
        ->parameters(['driver-invoices' => 'driverInvoice'])
        ->middleware('permission:driver_invoices.read.all|driver_invoices.read.own');

    // Seller support / ticket management — custom routes registered before the resource
    Route::get('support-tickets/related', [SupportTicketController::class, 'relatedObjects'])
        ->name('support-tickets.related');
    Route::get('support-tickets/for-object', [SupportTicketController::class, 'forObject'])
        ->name('support-tickets.for-object');
    Route::post('support-tickets/{supportTicket}/messages', [SupportTicketController::class, 'storeMessage'])
        ->whereNumber('supportTicket')->name('support-tickets.messages.store');
    Route::post('support-tickets/{supportTicket}/assign', [SupportTicketController::class, 'assign'])
        ->whereNumber('supportTicket')->name('support-tickets.assign');
    Route::post('support-tickets/{supportTicket}/status', [SupportTicketController::class, 'updateStatus'])
        ->whereNumber('supportTicket')->name('support-tickets.status');
    Route::post('support-tickets/{supportTicket}/close', [SupportTicketController::class, 'close'])
        ->whereNumber('supportTicket')->name('support-tickets.close');
    Route::resource('support-tickets', SupportTicketController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->whereNumber('supportTicket')
        ->parameters(['support-tickets' => 'supportTicket'])
        ->middleware('permission:support.read.all|support.read.own|support.manage');

    Route::get('api-integrations', [ApiIntegrationController::class, 'index'])
        ->middleware('permission:orders.create')
        ->name('api-integrations.index');

    // Help Center. No permission guard: both pages document rules the reader is
    // already subject to, and hiding the contract from the people it binds
    // helps nobody.
    Route::get('help/partnership', [HelpCenterController::class, 'partnership'])
        ->name('help.partnership');
    Route::get('help/processes', [HelpCenterController::class, 'processes'])
        ->name('help.processes');

    // Interactive guides. No permission guard on the Help Center itself: the
    // catalog is already filtered per reader, and an empty list is a fine page.
    Route::get('guides', [GuideController::class, 'index'])->name('guides.index');
    Route::post('guides/{guide}/progress', [GuideController::class, 'store'])
        ->where('guide', '[a-z0-9-]+')
        ->name('guides.progress.store');
    Route::delete('guides/{guide}/progress', [GuideController::class, 'destroy'])
        ->where('guide', '[a-z0-9-]+')
        ->name('guides.progress.destroy');

    Route::controller(VelzonRoutesController::class)->group(function () {

        // dashboards
        // NOTE: "/" is intentionally handled by the public LandingController
        // (registered at the top of this file). The dashboard lives at "/dashboard".
        Route::get('/dashboard', 'dashboard')->middleware('permission:dashboard.view');

        // Route::get('/dashboard/analytics', 'dashboard_analytics');
        // Route::get('/dashboard/crm', 'dashboard_crm');
        // Route::get('/dashboard/crypto', 'dashboard_crypto');
        // Route::get('/dashboard/job', 'dashboard_job');
        // Route::get('/dashboard/projects', 'dashboard_projects');
        // Route::get('/dashboard/blog', 'dashboard_blog');

        // // apps calendar route
        // Route::get("/calendar", "calendar");
        // Route::get("/month-grid", "month_grid");

        // // apps chat route
        // Route::get("/chat", "chat");

        // // apps file manager route
        // Route::get("/apps-file-manager", "apps_file_manager");

        // // apps todo route
        // Route::get("/apps-todo", "apps_todo");

        // // apps email routes
        // Route::get("/mailbox", "mailbox");
        // Route::get("/email/email-basic", "email_basic");
        // Route::get("/email/email-ecommerce", "email_ecommerce");

        // // apps nft routes
        // Route::get("/apps/nft-auction", "nft_auction");
        // Route::get("/apps/nft-collection", "nft_collection");
        // Route::get("/apps/nft-create", "nft_create");
        // Route::get("/apps/nft-creators", "nft_creators");
        // Route::get("/apps/nft-explore", "nft_explore");
        // Route::get("/apps/nft-item-detail", "nft_item");
        // Route::get("/apps/nft-marketplace", "nft_marketplace");
        // Route::get("/apps/nft-ranking", "nft_ranking");
        // Route::get("/apps/nft-wallet", "nft_wallet");

        // // apps project routes
        // Route::get("/apps/projects-list", "projects_list");
        // Route::get("/apps/projects-overview", "projects_overview");
        // Route::get("/apps/projects-create", "projects_create");

        // // apps task routes
        // Route::get("/apps/tasks-details", "tasks_details");
        // Route::get("/apps/tasks-kanban", "tasks_kanban");
        // Route::get("/apps/tasks-list-view", "tasks_list_view");

        // // apps tickets routes
        // Route::get("/apps/tickets-details", "tickets_details");
        // Route::get("/apps/tickets-list", "tickets_list");

        // // apps other routes
        // Route::get("/apps/crm-contacts", "crm_contacts");
        // Route::get("/apps/crm-companies", "crm_companies");
        // Route::get("/apps/crm-deals", "crm_deals");
        // Route::get("/apps/crm-leads", "crm_leads");

        // // apps ecommerce routes
        // Route::get("/ecommerce/customers", "ecommerce_customers");
        // Route::get("/ecommerce/products", "ecommerce_products");
        // Route::get("/ecommerce/product-details", "ecommerce_product_details");
        // Route::get("/ecommerce/orders", "ecommerce_orders");
        // Route::get("/ecommerce/order-details", "ecommerce_order_details");
        // Route::get("/ecommerce/add-product", "ecommerce_add_product");
        // Route::get("/ecommerce/shopping-cart", "ecommerce_shopping_cart");
        // Route::get("/ecommerce/checkout", "ecommerce_checkout");
        // Route::get("/ecommerce/sellers", "ecommerce_sellers");
        // Route::get("/ecommerce/seller-details", "ecommerce_seller_details");

        // // apps crypto routes
        // Route::get("/crypto/buy-sell", "crypto_buy_sell");
        // Route::get("/crypto/kyc", "crypto_kyc");
        // Route::get("/crypto/ico", "crypto_ico");
        // Route::get("/crypto/orders", "crypto_orders");
        // Route::get("/crypto/wallet", "crypto_wallet");
        // Route::get("/crypto/transactions", "crypto_transactions");

        // // apps invoice routes
        // Route::get("/invoices/detail", "invoices_detail");
        // Route::get("/invoices/list", "invoices_list");
        // Route::get("/invoices/create", "invoices_create");

        // // apps jobs routes
        // Route::get("/jobs/application", "jobs_application");
        // Route::get("/jobs/candidate-grid", "jobs_candidate_grid");
        // Route::get("/jobs/candidate-lists", "jobs_candidate_lists");
        // Route::get("/jobs/categories", "jobs_categories");
        // Route::get("/jobs/companies-list", "jobs_companies_list");
        // Route::get("/jobs/details", "jobs_details");
        // Route::get("/jobs/grid-lists", "jobs_grid");
        // Route::get("/jobs/lists", "jobs_lists");
        // Route::get("/jobs/new", "jobs_new");
        // Route::get("/jobs/statistics", "jobs_statistics");

        // // apps api key route
        // Route::get("/apps-api-key", "apps_api_key");

        // // ui routes
        // Route::get("/ui/alerts", "ui_alerts");
        // Route::get("/ui/buttons", "ui_buttons");
        // Route::get("/ui/cards", "ui_cards");
        // Route::get("/ui/carousel", "ui_carousel");
        // Route::get("/ui/dropdowns", "ui_dropdowns");
        // Route::get("/ui/grid", "ui_grid");
        // Route::get("/ui/images", "ui_images");
        // Route::get("/ui/modals", "ui_modals");
        // Route::get("/ui/offcanvas", "ui_offcanvas");
        // Route::get("/ui/progress", "ui_progress");
        // Route::get("/ui/placeholders", "ui_placeholders");
        // Route::get("/ui/accordions", "ui_accordions");
        // Route::get("/ui/tabs", "ui_tabs");
        // Route::get("/ui/typography", "ui_typography");
        // Route::get("/ui/embed-video", "ui_embed_video");
        // Route::get("/ui/ribbons", "ui_ribbons");
        // Route::get("/ui/lists", "ui_lists");
        // Route::get("/ui/links", "ui_links");
        // Route::get("/ui/utilities", "ui_utilities");
        // Route::get("/ui/notifications", "ui_notifications");
        // Route::get("/ui/general", "ui_general");
        // Route::get("/ui/colors", "ui_colors");
        // Route::get("/ui/badges", "ui_badges");
        // Route::get("/ui/media", "ui_media");

        // // widget route
        // Route::get("/widgets", "widgets");

        // // icons route
        // Route::get("/icons/boxicons", "icons_boxicons");
        // Route::get("/icons/materialdesign", "icons_materialdesign");
        // Route::get("/icons/feather", "icons_feather");
        // Route::get("/icons/lineawesome", "icons_lineawesome");
        // Route::get("/icons/remix", "icons_remix");
        // Route::get("/icons/crypto", "icons_crypto");

        // // tables route
        // Route::get("/tables/basic", "tables_basic");
        // Route::get("/tables/gridjs", "tables_gridjs");

        // // forms route
        // Route::get("/form/advanced", "form_advanced");
        // Route::get("/form/elements", "form_elements");
        // Route::get("/form/layouts", "form_layouts");
        // Route::get("/form/editors", "form_editors");
        // Route::get("/form/file-uploads", "form_file_uploads");
        // Route::get("/form/validation", "form_validation");
        // Route::get("/form/wizard", "form_wizard");
        // Route::get("/form/masks", "form_masks");
        // Route::get("/form/pickers", "form_pickers");
        // Route::get("/form/range-sliders", "form_range_sliders");
        // Route::get("/form/select", "form_select");
        // Route::get("/form/checkboxs-radios", "form_checkboxs_radios");

        // // landing routes
        // Route::get("/landing", "landing");
        // Route::get("/nft-landing", "nft_landing");
        // Route::get("/job-landing", "job_landing");

        // // pages routes
        // Route::get("/pages/starter", "pages_starter");
        // Route::get("/pages/profile", "pages_profile");
        // Route::get("/pages/profile-setting", "pages_profile_setting");
        // Route::get("/pages/maintenance", "pages_maintenance");
        // Route::get("/pages/coming-soon", "pages_coming_soon");
        // Route::get("/pages/timeline", "pages_timeline");
        // Route::get("/pages/faqs", "pages_faqs");
        // Route::get("/pages/pricing", "pages_pricing");
        // Route::get("/pages/team", "pages_team");
        // Route::get("/pages/search-results", "pages_search_results");
        // Route::get("/pages/sitemap", "pages_sitemap");
        // Route::get("/pages/privacy-policy", "pages_privacy_policy");
        // Route::get("/pages/term-conditions", "pages_term_conditions");
        // Route::get("/pages/blogs/list-view", "pages_blog_list");
        // Route::get("/pages/blogs/grid-view", "pages_blog_grid");
        // Route::get("/pages/blogs/overview", "pages_blog_overview");

        // // charts routes
        // Route::get("/charts/chartjs", "charts_chartjs");
        // Route::get("/charts/echart", "charts_echart");
        // Route::get("/charts/apex-line", "charts_apex_line");
        // Route::get("/charts/apex-area", "charts_apex_area");
        // Route::get("/charts/apex-bar", "charts_apex_bar");
        // Route::get("/charts/apex-column", "charts_apex_column");
        // Route::get("/charts/apex-mixed", "charts_apex_mixed");
        // Route::get("/charts/apex-range-area", "charts_apex_range");
        // Route::get("/charts/apex-funnel", "charts_apex_funnel");
        // Route::get("/charts/apex-candlestick", "charts_apex_candlestick");
        // Route::get("/charts/apex-boxplot", "charts_apex_boxplot");
        // Route::get("/charts/apex-bubble", "charts_apex_bubble");
        // Route::get("/charts/apex-scatter", "charts_apex_scatter");
        // Route::get("/charts/apex-heatmap", "charts_apex_heatmap");
        // Route::get("/charts/apex-treemap", "charts_apex_treemap");
        // Route::get("/charts/apex-pie", "charts_apex_pie");
        // Route::get("/charts/apex-radialbar", "charts_apex_radialbar");
        // Route::get("/charts/apex-radar", "charts_apex_radar");
        // Route::get("/charts/apex-polararea", "charts_apex_polararea");
        // Route::get("/charts/apex-slope", "charts_apex_slope");

        // // advance ui route
        // Route::get("/advance-ui/animation", "advance_ui_animation");
        // Route::get("/advance-ui/highlight", "advance_ui_highlight");
        // Route::get("/advance-ui/scrollbar", "advance_ui_scrollbar");
        // Route::get("/advance-ui/scrollspy", "advance_ui_scrollspy");
        // Route::get("/advance-ui/sweetalerts", "advance_ui_sweetalerts");
        // Route::get("/advance-ui/swiper", "advance_ui_swiper");

        // // auth sample page routes
        // Route::get("/auth/signin-basic", "auth_signin_basic");
        // Route::get("/auth/signin-cover", "auth_signin_cover");
        // Route::get("/auth/signup-basic", "auth_signup_basic");
        // Route::get("/auth/signup-cover", "auth_signup_cover");
        // Route::get("/auth/reset-pwd-basic", "auth_reset_pwd_basic");
        // Route::get("/auth/reset-pwd-cover", "auth_reset_pwd_cover");
        // Route::get("/auth/create-pwd-basic", "auth_create_pwd_basic");
        // Route::get("/auth/create-pwd-cover", "auth_create_pwd_cover");
        // Route::get("/auth/lockscreen-basic", "auth_lockscreen_basic");
        // Route::get("/auth/lockscreen-cover", "auth_lockscreen_cover");
        // Route::get("/auth/twostep-basic", "auth_twostep_basic");
        // Route::get("/auth/twostep-cover", "auth_twostep_cover");
        // Route::get("/auth/404", "auth_404");
        // Route::get("/auth/500", "auth_500");
        // Route::get("/auth/404-basic", "auth_404_basic");
        // Route::get("/auth/404-cover", "auth_404_cover");
        // Route::get("/auth/ofline", "auth_ofline");
        // Route::get("/auth/logout-basic", "auth_logout_basic");
        // Route::get("/auth/logout-cover", "auth_logout_cover");
        // Route::get("/auth/success-msg-basic", "auth_success_msg_basic");
        // Route::get("/auth/success-msg-cover", "auth_success_msg_cover");

        // // maps routes
        // Route::get("/maps/amcharts", "maps_amcharts");
        // Route::get("/maps/google", "maps_google");
        // Route::get("/maps/leaflet", "maps_leaflet");
    });
});
