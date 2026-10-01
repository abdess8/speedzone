<?php

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

test('a forbidden screen is an inertia page that keeps the application chrome', function () {
    $driver = StockFixtures::user(Role::DRIVER);

    $this->actingAs($driver)
        ->get('/products/create')
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('errors/Error')
            ->where('status', 403)
            ->where('homeUrl', url('/dashboard'))
        );
});

test('an unknown address is an inertia 404 with a way home', function () {
    $seller = StockFixtures::user(Role::SELLER);

    $this->actingAs($seller)
        ->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('errors/Error')
            ->where('status', 404)
            ->where('homeUrl', url('/dashboard'))
        );
});

test('a guest 404 still renders the branded error page', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('errors/Error')
            ->where('status', 404)
            ->where('homeUrl', route('login'))
        );
});

test('json clients still receive a json 403 rather than an inertia page', function () {
    $driver = StockFixtures::user(Role::DRIVER);

    $this->actingAs($driver)
        ->getJson('/products/create')
        ->assertForbidden()
        ->assertHeaderMissing('X-Inertia');
});
