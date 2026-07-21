<?php

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('registration screen cannot be rendered if support is disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
})->skip(function () {
    return Features::enabled(Features::registration());
}, 'Registration support is enabled.');

test('new users can register', function () {
    $city = City::query()->create([
        'name' => 'Register City',
        'code' => 'RGC',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'phone_number' => '+212611111111',
        'city_id' => $city->id,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice'));

    expect(User::query()->where('email', 'test@example.com')->first()?->isSeller())->toBeTrue();
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');
