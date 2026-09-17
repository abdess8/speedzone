<?php

use App\Models\Role;
use App\Notifications\ResetSpeedZonePasswordEmail;
use App\Notifications\VerifySpeedZoneAccountEmail;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

test('an admin can send a verification email to any user', function () {
    Notification::fake();

    $admin = StockFixtures::user(Role::ADMIN);
    $target = StockFixtures::user(Role::SELLER);
    $target->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($admin)
        ->get(route('users.show', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/show')
            ->where('can.update', true)
        );

    $this->actingAs($admin)
        ->from(route('users.show', $target))
        ->post(route('users.verification.send', $target))
        ->assertRedirect(route('users.show', $target))
        ->assertSessionHas('success');

    Notification::assertSentTo($target, VerifySpeedZoneAccountEmail::class);
});

test('an admin can request a password change for any user', function () {
    Notification::fake();

    $admin = StockFixtures::user(Role::ADMIN);
    $target = StockFixtures::user(Role::SELLER);

    $this->actingAs($admin)
        ->from(route('users.show', $target))
        ->post(route('users.password.reset.send', $target))
        ->assertRedirect(route('users.show', $target))
        ->assertSessionHas('success');

    Notification::assertSentTo($target, ResetSpeedZonePasswordEmail::class);
});

test('a seller cannot send account emails to another user', function () {
    Notification::fake();

    $seller = StockFixtures::user(Role::SELLER);
    $target = StockFixtures::user(Role::DRIVER);

    $this->actingAs($seller)
        ->post(route('users.verification.send', $target))
        ->assertForbidden();

    $this->actingAs($seller)
        ->post(route('users.password.reset.send', $target))
        ->assertForbidden();

    Notification::assertNothingSent();
});
