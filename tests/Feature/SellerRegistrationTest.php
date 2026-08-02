<?php

use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function sellerRegistrationCity(): City
{
    return City::query()->create([
        'name' => 'Registration City',
        'code' => 'REG',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

test('seller registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('new sellers can register with seller profile fields', function () {
    Event::fake([Verified::class]);

    $city = sellerRegistrationCity();

    $response = $this->post('/register', [
        'first_name' => 'Amine',
        'last_name' => 'Benali',
        'email' => 'seller-register-test@example.com',
        'phone_number' => '+212600000000',
        'city_id' => $city->id,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'seller-register-test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->status)->toBe(UserStatus::PendingEmailVerification)
        ->and($user->isSeller())->toBeTrue()
        ->and($user->city_id)->toBe($city->id);

    $response->assertRedirect(route('verification.notice'));
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('unverified sellers cannot log in', function () {
    $city = sellerRegistrationCity();

    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    User::factory()->create([
        'email' => 'unverified-seller@example.com',
        'password' => Hash::make('Password123!'),
        'city_id' => $city->id,
        'role_id' => $sellerRole->id,
        'status' => UserStatus::PendingEmailVerification,
        'email_verified_at' => null,
    ])->roles()->sync([$sellerRole->id]);

    $response = $this->post('/login', [
        'email' => 'unverified-seller@example.com',
        'password' => 'Password123!',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('verified pending sellers are redirected to pending approval page', function () {
    $city = sellerRegistrationCity();

    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $user = User::factory()->create([
        'email' => 'pending-seller@example.com',
        'password' => Hash::make('Password123!'),
        'city_id' => $city->id,
        'role_id' => $sellerRole->id,
        'status' => UserStatus::PendingApproval,
        'email_verified_at' => now(),
    ]);
    $user->roles()->sync([$sellerRole->id]);

    $response = $this->post('/login', [
        'email' => 'pending-seller@example.com',
        'password' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('account.pending-approval'));
});

test('active sellers are redirected to seller dashboard', function () {
    $city = sellerRegistrationCity();

    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $user = User::factory()->create([
        'email' => 'active-seller@example.com',
        'password' => Hash::make('Password123!'),
        'city_id' => $city->id,
        'role_id' => $sellerRole->id,
        'status' => UserStatus::Active,
        'email_verified_at' => now(),
    ]);
    $user->roles()->sync([$sellerRole->id]);

    $response = $this->post('/login', [
        'email' => 'active-seller@example.com',
        'password' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard.seller'));
});
