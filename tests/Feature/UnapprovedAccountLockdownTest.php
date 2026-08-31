<?php

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Notifications\VerifySpeedZoneAccountEmail;
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

function sellerAwaitingApproval(array $attributes = []): User
{
    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $user = User::factory()->create(array_merge([
        'role_id' => $sellerRole->id,
        'status' => UserStatus::PendingApproval,
        'email_verified_at' => now(),
    ], $attributes));

    $user->roles()->sync([$sellerRole->id]);

    return $user;
}

test('an account waiting for approval cannot open the dashboard', function () {
    $this->actingAs(sellerAwaitingApproval())
        ->get('/dashboard')
        ->assertRedirect(route('account.pending-approval'));
});

test('an account waiting for approval cannot open the profile screen', function () {
    $this->actingAs(sellerAwaitingApproval())
        ->get(route('profile.show'))
        ->assertRedirect(route('account.pending-approval'));
});

test('an account waiting for approval cannot edit anything but its email', function () {
    $seller = sellerAwaitingApproval();

    $this->actingAs($seller)
        ->put(route('user-profile-information.update'), [
            'name' => 'Renamed',
            'email' => $seller->email,
        ])
        ->assertForbidden();

    expect($seller->fresh()->name)->not->toBe('Renamed');
});

test('an account waiting for approval can change its email and is asked to verify it again', function () {
    Notification::fake();

    $seller = sellerAwaitingApproval(['email' => 'typo@example.com']);

    $response = $this->actingAs($seller)->put(route('account.email.update'), [
        'email' => 'correct@example.com',
    ]);

    $response->assertRedirect(route('verification.notice'));

    $seller->refresh();

    expect($seller->email)->toBe('correct@example.com')
        ->and($seller->hasVerifiedEmail())->toBeFalse()
        ->and($seller->status)->toBe(UserStatus::PendingEmailVerification);

    Notification::assertSentTo($seller, VerifySpeedZoneAccountEmail::class);
});

test('the new email must not already belong to someone else', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $seller = sellerAwaitingApproval(['email' => 'mine@example.com']);

    $this->actingAs($seller)
        ->put(route('account.email.update'), ['email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');

    expect($seller->fresh()->email)->toBe('mine@example.com');
});

test('an approved account has no business on the holding page email form', function () {
    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $seller = User::factory()->create([
        'role_id' => $sellerRole->id,
        'status' => UserStatus::Active,
        'email_verified_at' => now(),
    ]);
    $seller->roles()->sync([$sellerRole->id]);

    $this->actingAs($seller)
        ->put(route('account.email.update'), ['email' => 'whatever@example.com'])
        ->assertForbidden();
});
