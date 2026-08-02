<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans the rich text an administrator writes for an alert.
 *
 * The message is rendered with `v-html` on every user's screen, so whatever
 * survives this class executes in their browser. Only the formatting the editor
 * actually offers is allowed through; everything else — scripts, iframes, event
 * handlers, `javascript:` URLs — is dropped rather than escaped, so a mistake
 * here degrades into plain text instead of into a stored XSS.
 */
class AlertHtml
{
    public static function sanitize(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $config = (new HtmlSanitizerConfig)
            ->allowElement('p', ['style'])
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('span', ['style'])
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('h2', ['style'])
            ->allowElement('h3', ['style'])
            ->allowElement('h4', ['style'])
            ->allowElement('blockquote')
            ->allowElement('a', ['href', 'title'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->forceAttribute('a', 'target', '_blank')
            // An alert can be shown to anyone, so links out of the app must not
            // hand the destination a handle on our window.
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            // Colour and size are part of the brief, so `style` has to survive —
            // but only the declarations InlineStyleSanitizer recognises.
            ->withAttributeSanitizer(new InlineStyleSanitizer)
            ->withMaxInputLength(200_000);

        return trim((new HtmlSanitizer($config))->sanitize($html));
    }

    /**
     * The message as plain text, for table listings and previews where markup
     * would only get in the way.
     */
    public static function toText(?string $html, int $limit = 120): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5));
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return mb_strimwidth($text, 0, $limit, '…');
    }
}
