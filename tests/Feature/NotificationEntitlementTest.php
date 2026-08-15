<?php

use App\Enums\NotificationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Support\NotificationPermissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->service = app(NotificationPreferenceService::class);
});

function topicUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

it('keeps a new vendor sign-up on the administration desk', function () {
    $admin = topicUser(Role::ADMIN);
    $seller = topicUser(Role::SELLER);
    $driver = topicUser(Role::DRIVER);

    expect($this->service->isEnabled($admin, NotificationType::SellerRegistered))->toBeTrue()
        ->and($this->service->isEnabled($seller, NotificationType::SellerRegistered))->toBeFalse()
        ->and($this->service->isEnabled($driver, NotificationType::SellerRegistered))->toBeFalse();
});

it('does not talk to a driver about invoices', function () {
    $driver = topicUser(Role::DRIVER);

    expect($this->service->isEnabled($driver, NotificationType::InvoiceGenerated))->toBeFalse()
        ->and($this->service->isEnabled(topicUser(Role::SELLER), NotificationType::InvoiceGenerated))->toBeTrue();
});

it('only offers a driver the switches that concern his round', function () {
    $topics = $this->service->editableKeys(topicUser(Role::DRIVER));

    expect($topics)->toContain('enabled')
        ->and($topics)->toContain(NotificationType::StockPickupRequested->value)
        ->and($topics)->not->toContain(NotificationType::SellerRegistered->value)
        ->and($topics)->not->toContain(NotificationType::InvoiceGenerated->value);
});

it('drops a switch the user was never offered', function () {
    $driver = topicUser(Role::DRIVER);

    $this->actingAs($driver)
        ->putJson(route('notification-preferences.update'), [
            'enabled' => true,
            NotificationType::SellerRegistered->value => true,
        ])
        ->assertOk()
        ->assertJsonMissingPath('data.'.NotificationType::SellerRegistered->value);

    expect($this->service->isEnabled($driver->fresh(['roles.permissions']), NotificationType::SellerRegistered))
        ->toBeFalse();
});

it('stores the switches the user does own', function () {
    $seller = topicUser(Role::SELLER);

    $this->actingAs($seller)
        ->putJson(route('notification-preferences.update'), [
            'enabled' => true,
            NotificationType::InvoiceGenerated->value => false,
        ])
        ->assertOk();

    expect($this->service->isEnabled($seller->fresh(['roles.permissions']), NotificationType::InvoiceGenerated))
        ->toBeFalse();
});

it('silences every topic when the master switch is off', function () {
    $seller = topicUser(Role::SELLER);

    $this->service->update($seller, ['enabled' => false]);

    expect($this->service->isEnabled($seller, NotificationType::InvoiceGenerated))->toBeFalse();
});

it('lets an operator hand a single topic to one person', function () {
    $dispatcher = topicUser(Role::DISPATCHER);

    expect($this->service->isEnabled($dispatcher, NotificationType::SellerRegistered))->toBeFalse();

    $dispatcher->permissions()->syncWithoutDetaching(
        Permission::query()
            ->where('name', NotificationPermissions::for(NotificationType::SellerRegistered))
            ->pluck('id')
            ->all()
    );

    expect($this->service->isEnabled($dispatcher->fresh(['roles.permissions', 'permissions']), NotificationType::SellerRegistered))
        ->toBeTrue();
});
