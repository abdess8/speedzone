<?php

use App\Models\User;
use App\Notifications\ResetSpeedZonePasswordEmail;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetSpeedZonePasswordEmail::class);
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetSpeedZonePasswordEmail::class, function (object $notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');

test('the password reset email is written in French', function () {
    $user = User::factory()->make([
        'first_name' => 'Amine',
        'locale' => 'fr',
        'email' => 'amine@example.test',
    ]);

    $mail = (new ResetSpeedZonePasswordEmail('test-token'))->toMail($user);

    $previous = app()->getLocale();
    app()->setLocale('fr');
    $html = (string) app(Markdown::class)->render('notifications::email', $mail->toArray());
    app()->setLocale($previous);

    expect($mail->subject)->toBe('Réinitialisation de votre mot de passe SpeedZone Express')
        ->and($mail->greeting)->toBe('Bonjour Amine,')
        ->and($mail->actionText)->toBe('Réinitialiser le mot de passe')
        ->and($html)->toContain('demande de réinitialisation')
        ->and($html)->toContain('expire dans')
        ->and($html)->toContain('aucune action n')
        ->and($html)->toContain('Cordialement')
        ->and($html)->not->toContain('Reset Password Notification')
        ->and($html)->not->toContain('You are receiving this email because we received a password reset request');
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetSpeedZonePasswordEmail::class, function (object $notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertSessionHasNoErrors();

        return true;
    });
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');
