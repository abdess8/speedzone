<?php

namespace App\Listeners;

use App\Support\MailBranding;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * Attaches the SpeedZone logo as an inline CID part whenever a message
 * references it. Markdown mail is rendered before the Symfony message exists,
 * so the template can only put `cid:…` in the HTML — this listener supplies
 * the bytes that go with it.
 */
class EmbedSpeedZoneMailLogo
{
    public function handle(MessageSending $event): void
    {
        $html = $event->message->getHtmlBody();
        $cid = MailBranding::LOGO_CID;

        if (! is_string($html) || ! str_contains($html, 'cid:'.$cid)) {
            return;
        }

        foreach ($event->message->getAttachments() as $part) {
            if ($part instanceof DataPart && $part->hasContentId() && $part->getContentId() === $cid) {
                return;
            }
        }

        $path = MailBranding::logoPath();

        if (! is_file($path)) {
            return;
        }

        $event->message->addPart(
            (new DataPart(new File($path), 'speedzone-logo.png', 'image/png'))
                ->asInline()
                ->setContentId($cid)
        );
    }
}
