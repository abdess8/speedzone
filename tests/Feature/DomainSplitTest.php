<?php

use App\Models\User;
use App\Support\LoginRedirect;
use Inertia\Testing\AssertableInertia as Assert;

function enableDomainSplit(): void
{
    config([
        'app.url' => 'https://app.speedzoneexpress.ma',
        'domains.app_host' => 'app.speedzoneexpress.ma',
        'domains.marketing_host' => 'speedzoneexpress.ma',
        'domains.app_url' => 'https://app.speedzoneexpress.ma',
        'domains.marketing_url' => 'https://speedzoneexpress.ma',
    ]);
}

test('the marketing host still serves the public landing page', function () {
    enableDomainSplit();

    $this->get('https://speedzoneexpress.ma/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('landing/Home'));
});

test('back-office paths on the marketing host redirect to the app host', function () {
    enableDomainSplit();

    $this->get('https://speedzoneexpress.ma/login')
        ->assertRedirect('https://app.speedzoneexpress.ma/login');

    $this->get('https://speedzoneexpress.ma/dashboard')
        ->assertRedirect('https://app.speedzoneexpress.ma/dashboard');
});

test('the app host root sends guests to login', function () {
    enableDomainSplit();

    $this->get('https://app.speedzoneexpress.ma/')
        ->assertRedirect('https://app.speedzoneexpress.ma/login');
});

test('the app host root sends signed-in users to the dashboard', function () {
    enableDomainSplit();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('https://app.speedzoneexpress.ma/')
        ->assertRedirect(LoginRedirect::forUser($user));
});

test('public tracking on the app host redirects to the marketing site', function () {
    enableDomainSplit();

    $this->get('https://app.speedzoneexpress.ma/tracking/SPD-2026-123456')
        ->assertRedirect('https://speedzoneexpress.ma/tracking/SPD-2026-123456');
});

test('www on the marketing host canonicalizes to the apex', function () {
    enableDomainSplit();

    $this->get('https://www.speedzoneexpress.ma/')
        ->assertRedirect('https://speedzoneexpress.ma/');
});

test('login on the app host is not redirected away', function () {
    enableDomainSplit();

    $this->get('https://app.speedzoneexpress.ma/login')
        ->assertOk();
});
