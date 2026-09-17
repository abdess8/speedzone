<?php

namespace App\Support;

/**
 * Shared identifiers for branded HTML emails.
 *
 * The logo is attached inline (CID) rather than loaded from APP_URL, so Gmail
 * and others can render it even when the app is running on localhost.
 */
final class MailBranding
{
    public const LOGO_CID = 'logo@speedzoneexpress.ma';

    public static function logoPath(): string
    {
        $relative = ltrim((string) config('company.logo', 'images/logo-dark.png'), '/');

        return public_path($relative);
    }
}
