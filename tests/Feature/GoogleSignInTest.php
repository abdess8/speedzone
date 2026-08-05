<?php

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    config()->set('services.google.client_id', 'test-client-id');
    config()->set('services.google.client_secret', 'test-client-secret');
});

/**
 * Stand in for the round trip to Google's consent screen.
 */
function fakeGoogleUser(string $email, string $id = 'google-123', string $name = 'Amine Benali'): void
{
    $googleUser = (new SocialiteUser)->map([
        'id' => $id,
        'name' => $name,
        'email' => $email,
    ]);

    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('a first sign-in with google creates a seller waiting for approval', function () {
    fakeGoogleUser('new-google-seller@example.com');

    $response = $this->get('/auth/google/callback');

    $this->assertAuthenticated();
    $response->assertRedirect(route('account.pending-approval'));

    $user = User::query()->where('email', 'new-google-seller@example.com')->firstOrFail();

    expect($user->google_id)->toBe('google-123')
        ->and($user->status)->toBe(UserStatus::PendingApproval)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->isSeller())->toBeTrue()
        ->and($user->first_name)->toBe('Amine')
        ->and($user->last_name)->toBe('Benali');
});

test('google signs an existing account in and links it', function () {
    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $user = User::factory()->create([
        'email' => 'existing-seller@example.com',
        'password' => Hash::make('Password123'),
        'role_id' => $sellerRole->id,
        'status' => UserStatus::Active,
        'email_verified_at' => now(),
    ]);
    $user->roles()->sync([$sellerRole->id]);

    fakeGoogleUser('existing-seller@example.com', 'google-456');

    $response = $this->get('/auth/google/callback');

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard.seller'));

    expect($user->fresh()->google_id)->toBe('google-456')
        ->and(User::query()->count())->toBe(1);
});

test('google verifies the address of an account that never confirmed it', function () {
    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $user = User::factory()->create([
        'email' => 'unverified@example.com',
        'role_id' => $sellerRole->id,
        'status' => UserStatus::PendingEmailVerification,
        'email_verified_at' => null,
    ]);
    $user->roles()->sync([$sellerRole->id]);

    fakeGoogleUser('unverified@example.com', 'google-789');

    $this->get('/auth/google/callback');

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->status)->toBe(UserStatus::PendingApproval);
});

test('a rejected registration cannot come back through google', function () {
    $user = User::factory()->create([
        'email' => 'rejected@example.com',
        'status' => UserStatus::Rejected,
        'email_verified_at' => now(),
    ]);

    fakeGoogleUser('rejected@example.com', 'google-999');

    $response = $this->get('/auth/google/callback');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

test('google sign-in is refused while no credentials are configured', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    $response = $this->get('/auth/google/redirect');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});
