<?php

use App\Models\User;
use App\Notifications\VerifySpeedZoneAccountEmail;
use Illuminate\Mail\Markdown;

it('writes the verification email in French', function () {
    $user = User::factory()->create([
        'first_name' => 'Amine',
        'locale' => 'fr',
        'email_verified_at' => null,
    ]);

    $mail = (new VerifySpeedZoneAccountEmail)->toMail($user);

    $previous = app()->getLocale();
    app()->setLocale('fr');
    $html = (string) app(Markdown::class)->render('notifications::email', $mail->toArray());
    app()->setLocale($previous);

    expect($mail->subject)->toBe('Vérifiez votre compte Speed Zone')
        ->and($mail->greeting)->toBe('Bonjour !')
        ->and($mail->actionText)->toBe('Vérifier l\'adresse e-mail')
        ->and($html)->toContain('Cliquez sur le bouton ci-dessous')
        ->and($html)->toContain('aucune action n')
        ->and($html)->toContain('Cordialement')
        ->and($html)->toContain('navigateur')
        ->and($html)->not->toContain('Please click the button below')
        ->and($html)->not->toContain('Hello!');
});
