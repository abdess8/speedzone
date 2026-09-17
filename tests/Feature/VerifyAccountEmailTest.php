<?php

use App\Listeners\EmbedSpeedZoneMailLogo;
use App\Models\User;
use App\Notifications\VerifySpeedZoneAccountEmail;
use App\Support\MailBranding;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Markdown;
use Symfony\Component\Mime\Email;

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

    expect($mail->subject)->toBe('Vérifiez votre compte SpeedZone Express')
        ->and($mail->greeting)->toBe('Bonjour Amine,')
        ->and($mail->actionText)->toBe('Vérifier l\'adresse e-mail')
        ->and($html)->toContain('Cliquez sur le bouton ci-dessous')
        ->and($html)->toContain('aucune action n')
        ->and($html)->toContain('Cordialement')
        ->and($html)->toContain('SpeedZone Express')
        ->and($html)->toContain('cid:logo@speedzoneexpress.ma')
        ->and($html)->toContain('#0d4a9d')
        ->and($html)->toContain('#f15a24')
        ->and($html)->toContain('navigateur')
        ->and($html)->not->toContain('Please click the button below')
        ->and($html)->not->toContain('Hello!');
});

it('embeds the SpeedZone logo as an inline CID attachment', function () {
    $email = (new Email)
        ->html('<img src="cid:'.MailBranding::LOGO_CID.'" alt="SpeedZone Express">');

    (new EmbedSpeedZoneMailLogo)->handle(
        new MessageSending($email)
    );

    $parts = $email->getAttachments();

    expect($parts)->toHaveCount(1)
        ->and($parts[0]->getContentId())->toBe(MailBranding::LOGO_CID)
        ->and($parts[0]->getMediaType())->toBe('image')
        ->and(is_file(MailBranding::logoPath()))->toBeTrue();
});
