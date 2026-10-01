<?php

use App\Models\User;
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
        ->assertInertia(fn (Assert $page) => $page
            ->component('landing/Home')
            ->where('authenticated', false)
        );
});

test('a signed-in visitor sees the dashboard CTA on the marketing site', function () {
    enableDomainSplit();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('https://speedzoneexpress.ma/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landing/Home')
            ->where('authenticated', true)
        );
});

test('back-office paths on the marketing host redirect to the app host', function () {
    enableDomainSplit();

    $this->get('https://speedzoneexpress.ma/login')
        ->assertRedirect('https://app.speedzoneexpress.ma/login');

    $this->get('https://speedzoneexpress.ma/dashboard')
        ->assertRedirect('https://app.speedzoneexpress.ma/dashboard');
});

test('the app host root always sends visitors to login', function () {
    enableDomainSplit();

    $this->get('https://app.speedzoneexpress.ma/')
        ->assertRedirect('https://app.speedzoneexpress.ma/login');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('https://app.speedzoneexpress.ma/')
        ->assertRedirect('https://app.speedzoneexpress.ma/login');
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
