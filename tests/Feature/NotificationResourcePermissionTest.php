<?php

use App\Enums\InvoiceStatus;
use App\Enums\NotificationType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Events\InvoiceGenerated;
use App\Events\NewSellerRegistered;
use App\Events\TicketCreated;
use App\Listeners\SendInvoiceNotification;
use App\Listeners\SendTicketNotification;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\InvoiceGeneratedNotification;
use App\Notifications\NewSellerRegistrationNotification;
use App\Notifications\TicketCreatedNotification;
use App\Services\TeamRoleService;
use App\Services\TeamService;
use App\Support\NotificationPermissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function gatedUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions', 'permissions']);
}

/**
 * @param  array<int, string>  $names
 */
function grantNamedPermissions(User $user, array $names): User
{
    $user->permissions()->syncWithoutDetaching(
        Permission::query()->whereIn('name', $names)->pluck('id')->all()
    );
    $user->forgetAccessMemo();

    return $user->fresh(['roles.permissions', 'permissions', 'stores']);
}

function gatedStore(User $owner, string $name): Store
{
    $store = Store::query()->create([
        'owner_id' => $owner->id,
        'name' => $name,
        'is_default' => ! Store::query()->where('owner_id', $owner->id)->exists(),
        'is_active' => true,
    ]);

    $store->users()->syncWithoutDetaching([$owner->id]);

    return $store;
}

it('keeps a new seller sign-up off a vendor who cannot manage users', function () {
    Notification::fake();

    $admin = gatedUser(Role::ADMIN);
    $seller = gatedUser(Role::SELLER);
    $newSeller = User::factory()->create();

    event(new NewSellerRegistered($newSeller));

    Notification::assertSentTo($admin, NewSellerRegistrationNotification::class);
    Notification::assertNotSentTo($seller, NewSellerRegistrationNotification::class);
});

it('does not tell a vendor about a sign-up just because he holds the topic grant', function () {
    Notification::fake();

    $seller = grantNamedPermissions(
        gatedUser(Role::SELLER),
        [NotificationPermissions::for(NotificationType::SellerRegistered)],
    );
    $newSeller = User::factory()->create();

    event(new NewSellerRegistered($newSeller));

    Notification::assertNotSentTo($seller, NewSellerRegistrationNotification::class);
});

it('tells an operator about a sign-up once he can both hear the topic and read users', function () {
    Notification::fake();

    $dispatcher = grantNamedPermissions(
        gatedUser(Role::DISPATCHER),
        [
            NotificationPermissions::for(NotificationType::SellerRegistered),
            'users.read',
        ],
    );
    $newSeller = User::factory()->create();

    event(new NewSellerRegistered($newSeller));

    Notification::assertSentTo($dispatcher, NewSellerRegistrationNotification::class);
});

it('does not tell a warehouse teammate that an invoice was issued', function () {
    Notification::fake();

    $vendor = gatedUser(Role::SELLER);
    $store = gatedStore($vendor, 'Boutique A');
    $role = app(TeamRoleService::class)->create($vendor, 'Préparateur', ['orders.read.own', 'orders.print']);

    $picker = app(TeamService::class)->create($vendor, [
        'first_name' => 'Yassine',
        'last_name' => 'B.',
        'email' => 'picker@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-PERM-1',
        'seller_id' => $vendor->id,
        'store_id' => $store->id,
        'status' => InvoiceStatus::GENERATED,
        'net_amount' => 120,
        'generated_at' => now(),
    ]);

    app(SendInvoiceNotification::class)->handle(new InvoiceGenerated($invoice));

    Notification::assertSentTo($vendor->fresh(['roles.permissions', 'permissions', 'stores']), InvoiceGeneratedNotification::class);
    Notification::assertNotSentTo($picker->fresh(['roles.permissions', 'permissions', 'stores']), InvoiceGeneratedNotification::class);
});

it('tells a teammate who can open invoices when one is issued for his shop', function () {
    Notification::fake();

    $vendor = gatedUser(Role::SELLER);
    $store = gatedStore($vendor, 'Boutique A');
    $role = app(TeamRoleService::class)->create($vendor, 'Comptable', [
        'invoices.read.own',
        NotificationPermissions::for(NotificationType::InvoiceGenerated),
    ]);

    $accountant = app(TeamService::class)->create($vendor, [
        'first_name' => 'Sara',
        'last_name' => 'K.',
        'email' => 'books@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-PERM-2',
        'seller_id' => $vendor->id,
        'store_id' => $store->id,
        'status' => InvoiceStatus::GENERATED,
        'net_amount' => 80,
        'generated_at' => now(),
    ]);

    app(SendInvoiceNotification::class)->handle(new InvoiceGenerated($invoice));

    Notification::assertSentTo($accountant->fresh(['roles.permissions', 'permissions', 'stores']), InvoiceGeneratedNotification::class);
});

it('does not tell a vendor that another shop opened a support ticket', function () {
    Notification::fake();

    $staff = gatedUser(Role::DISPATCHER);
    $seller = gatedUser(Role::SELLER);
    $other = gatedUser(Role::SELLER);

    $ticket = SupportTicket::query()->create([
        'reference' => 'SUP-2026-00001',
        'created_by' => $other->id,
        'category' => SupportTicketCategory::OTHER,
        'subject' => 'Late parcel',
        'message' => 'Where is it?',
        'status' => SupportTicketStatus::OPEN,
    ]);

    app(SendTicketNotification::class)->handleCreated(new TicketCreated($ticket));

    Notification::assertSentTo($staff, TicketCreatedNotification::class);
    Notification::assertNotSentTo($seller, TicketCreatedNotification::class);
    Notification::assertNotSentTo($other, TicketCreatedNotification::class);
});
