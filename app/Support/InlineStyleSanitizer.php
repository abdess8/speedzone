<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Reduces a `style` attribute to a short list of harmless declarations.
 *
 * Symfony's sanitizer treats `style` as opaque text: allow the attribute and
 * anything goes through. That is too much for content shown to every user —
 * `position:fixed;inset:0;z-index:9999` turns an alert into an overlay covering
 * the whole application, and `background:url(...)` calls out to a third party
 * from inside their session. Only the colour, size and alignment declarations
 * the editor offers survive.
 */
class InlineStyleSanitizer implements AttributeSanitizerInterface
{
    private const ALLOWED_PROPERTIES = [
        'color',
        'background-color',
        'font-size',
        'font-family',
        'font-weight',
        'font-style',
        'text-align',
        'text-decoration',
    ];

    /** Values that reach outside the declaration, whatever the property is. */
    private const FORBIDDEN_VALUES = '/url\s*\(|expression\s*\(|javascript:|@import|<|behaviour|behavior|\\\\/i';

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];

        foreach (explode(';', $value) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $declared] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $declared = trim($declared);

            if (! in_array($property, self::ALLOWED_PROPERTIES, true)) {
                continue;
            }

            if ($declared === '' || preg_match(self::FORBIDDEN_VALUES, $declared)) {
                continue;
            }

            $kept[] = $property.': '.$declared;
        }

        // Returning null drops the attribute entirely, which keeps the markup
        // clean when every declaration was rejected.
        return $kept === [] ? null : implode('; ', $kept);
    }
}
