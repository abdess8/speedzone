<?php

namespace App\Support;

/**
 * Makes Arabic customer data printable by Dompdf.
 *
 * Dompdf paints glyphs strictly left to right and implements neither the
 * Unicode bidirectional algorithm nor Arabic contextual shaping. Sellers who
 * type customer names and addresses in Arabic would therefore get labels and
 * invoices with disconnected letters in reverse order. This helper does the two
 * jobs the engine will not do:
 *
 *  1. shaping — every Arabic letter is replaced by its isolated, initial,
 *     medial or final presentation form (U+FE80..U+FEFC) and lam + alef pairs
 *     are fused into their mandatory ligature;
 *  2. reordering — the result is emitted in visual order, so painting it left
 *     to right yields correct right-to-left text, while embedded Latin words,
 *     phone numbers and amounts keep their own left-to-right order.
 *
 * Strings that contain no Arabic are returned untouched, so existing French and
 * English documents render exactly as before.
 */
final class ArabicText
{
    /**
     * Presentation forms per Arabic letter: [isolated, final, initial, medial].
     *
     * A null initial form marks a right-joining letter (alef, dal, reh, waw…)
     * which never connects to the letter that follows it.
     *
     * @var array<int, array{0: int, 1: ?int, 2: ?int, 3: ?int}>
     */
    private const FORMS = [
        0x0621 => [0xFE80, null, null, null],
        0x0622 => [0xFE81, 0xFE82, null, null],
        0x0623 => [0xFE83, 0xFE84, null, null],
        0x0624 => [0xFE85, 0xFE86, null, null],
        0x0625 => [0xFE87, 0xFE88, null, null],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, null, null],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, null, null],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, null, null],
        0x0630 => [0xFEAB, 0xFEAC, null, null],
        0x0631 => [0xFEAD, 0xFEAE, null, null],
        0x0632 => [0xFEAF, 0xFEB0, null, null],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, null, null],
        0x0649 => [0xFEEF, 0xFEF0, null, null],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
    ];

    /**
     * Mandatory lam + alef ligatures, keyed by the alef that follows the lam:
     * [isolated, final].
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    private const LAM = 0x0644;

    /**
     * Brackets and quotes whose glyph must be swapped inside a right-to-left run.
     *
     * @var array<int, int>
     */
    private const MIRRORED = [
        0x0028 => 0x0029, 0x0029 => 0x0028,
        0x005B => 0x005D, 0x005D => 0x005B,
        0x007B => 0x007D, 0x007D => 0x007B,
        0x003C => 0x003E, 0x003E => 0x003C,
        0x00AB => 0x00BB, 0x00BB => 0x00AB,
    ];

    /**
     * Render a value the way Dompdf must paint it.
     */
    public static function render(mixed $value): string
    {
        $text = (string) $value;

        if (! self::hasArabic($text)) {
            return $text;
        }

        return self::toVisualOrder(self::shape($text));
    }

    /**
     * Split a value into lines that are each safe to print on their own.
     *
     * Reordering happens per string, so a right-to-left value long enough to be
     * wrapped by the PDF engine would come out with its lines upside down: the
     * end of the address on the first line. Breaking the text ourselves, before
     * reordering, keeps the lines in reading order.
     *
     * Latin values are returned whole and left to the engine to wrap.
     *
     * @return array<int, string>
     */
    public static function lines(mixed $value, int $charactersPerLine): array
    {
        $text = (string) $value;

        if (! self::hasArabic($text) || mb_strlen($text) <= $charactersPerLine) {
            return [self::render($text)];
        }

        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) <= $charactersPerLine || $current === '') {
                $current = $candidate;

                continue;
            }

            $lines[] = self::render($current);
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = self::render($current);
        }

        return $lines;
    }

    /**
     * Whether the value must be laid out right to left.
     */
    public static function isRtl(mixed $value): bool
    {
        return self::hasArabic((string) $value);
    }

    /**
     * CSS class to append so the block is aligned on the right edge.
     */
    public static function cssClass(mixed $value): string
    {
        return self::isRtl($value) ? 'rtl' : '';
    }

    private static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /**
     * Replace every Arabic letter by the presentation form its neighbours call
     * for, and fuse lam + alef pairs. Vowel marks are dropped: Dompdf stacks
     * combining marks as spacing glyphs, which would tear the word apart.
     *
     * @return array<int, int> codepoints, still in logical order
     */
    private static function shape(string $text): array
    {
        /** @var array<int, int> $letters */
        $letters = [];

        foreach (mb_str_split($text, 1, 'UTF-8') as $character) {
            $codepoint = mb_ord($character, 'UTF-8');

            if ($codepoint === false || self::isTransparent($codepoint)) {
                continue;
            }

            $letters[] = $codepoint;
        }

        $shaped = [];
        $count = count($letters);

        for ($i = 0; $i < $count; $i++) {
            $current = $letters[$i];
            $next = $letters[$i + 1] ?? null;

            if (! isset(self::FORMS[$current])) {
                $shaped[] = $current;

                continue;
            }

            $joinsLeft = self::connectsForward($letters[$i - 1] ?? null) && self::acceptsFromRight($current);

            if ($current === self::LAM && $next !== null && isset(self::LAM_ALEF[$next])) {
                $shaped[] = self::LAM_ALEF[$next][$joinsLeft ? 1 : 0];
                $i++;

                continue;
            }

            $joinsRight = self::connectsForward($current) && self::acceptsFromRight($next);

            $form = match (true) {
                $joinsLeft && $joinsRight => 3,
                $joinsLeft => 1,
                $joinsRight => 2,
                default => 0,
            };

            $shaped[] = self::FORMS[$current][$form] ?? self::FORMS[$current][0];
        }

        return $shaped;
    }

    /**
     * Reorder shaped codepoints so that painting them left to right displays
     * right-to-left text, Latin and numeric runs excepted.
     *
     * @param  array<int, int>  $codepoints
     */
    private static function toVisualOrder(array $codepoints): string
    {
        $directions = self::resolveDirections($codepoints);
        $count = count($codepoints);

        for ($i = 0; $i < $count; $i++) {
            if ($directions[$i] === 'R' && isset(self::MIRRORED[$codepoints[$i]])) {
                $codepoints[$i] = self::MIRRORED[$codepoints[$i]];
            }
        }

        $codepoints = array_reverse($codepoints);
        $directions = array_reverse($directions);

        // The global reversal also flipped the embedded left-to-right runs;
        // flip each of them back so words and numbers stay readable.
        for ($i = 0; $i < $count; $i++) {
            if ($directions[$i] !== 'L') {
                continue;
            }

            $start = $i;
            while ($i + 1 < $count && $directions[$i + 1] === 'L') {
                $i++;
            }

            $run = array_reverse(array_slice($codepoints, $start, $i - $start + 1));
            array_splice($codepoints, $start, $i - $start + 1, $run);
        }

        return implode('', array_map(static fn (int $codepoint) => mb_chr($codepoint, 'UTF-8'), $codepoints));
    }

    /**
     * Give every codepoint a direction. Spaces and punctuation only stay with a
     * left-to-right run when they sit between two of its characters, otherwise
     * they follow the right-to-left paragraph direction.
     *
     * @param  array<int, int>  $codepoints
     * @return array<int, string>
     */
    private static function resolveDirections(array $codepoints): array
    {
        $directions = array_map(
            static fn (int $codepoint) => match (true) {
                self::isLeftToRight($codepoint) => 'L',
                self::isArabicCodepoint($codepoint) => 'R',
                default => 'N',
            },
            $codepoints
        );

        $count = count($directions);

        for ($i = 0; $i < $count; $i++) {
            if ($directions[$i] !== 'N') {
                continue;
            }

            $start = $i;
            while ($i + 1 < $count && $directions[$i + 1] === 'N') {
                $i++;
            }

            $before = $start > 0 ? $directions[$start - 1] : 'R';
            $after = $i + 1 < $count ? $directions[$i + 1] : 'R';
            $resolved = ($before === 'L' && $after === 'L') ? 'L' : 'R';

            for ($j = $start; $j <= $i; $j++) {
                $directions[$j] = $resolved;
            }
        }

        return $directions;
    }

    /**
     * Vowel marks, tatweel-less diacritics and joiner controls: they take part
     * in neither shaping decisions nor ordering.
     */
    private static function isTransparent(int $codepoint): bool
    {
        return ($codepoint >= 0x064B && $codepoint <= 0x065F)
            || $codepoint === 0x0670
            || ($codepoint >= 0x06D6 && $codepoint <= 0x06ED)
            || $codepoint === 0x200C
            || $codepoint === 0x200D
            || ($codepoint >= 0x200E && $codepoint <= 0x200F);
    }

    /**
     * Whether the letter connects to the one after it (dual-joining).
     */
    private static function connectsForward(?int $codepoint): bool
    {
        return $codepoint !== null && (self::FORMS[$codepoint][2] ?? null) !== null;
    }

    /**
     * Whether the letter accepts a connection from the one before it.
     */
    private static function acceptsFromRight(?int $codepoint): bool
    {
        return $codepoint !== null && (self::FORMS[$codepoint][1] ?? null) !== null;
    }

    private static function isArabicCodepoint(int $codepoint): bool
    {
        return ($codepoint >= 0x0600 && $codepoint <= 0x06FF)
            || ($codepoint >= 0x0750 && $codepoint <= 0x077F)
            || ($codepoint >= 0x08A0 && $codepoint <= 0x08FF)
            || ($codepoint >= 0xFB50 && $codepoint <= 0xFDFF)
            || ($codepoint >= 0xFE70 && $codepoint <= 0xFEFF);
    }

    /**
     * Latin letters and digits. Arabic-Indic digits count too: numbers are read
     * left to right whatever the surrounding script.
     */
    private static function isLeftToRight(int $codepoint): bool
    {
        return ($codepoint >= 0x0030 && $codepoint <= 0x0039)
            || ($codepoint >= 0x0041 && $codepoint <= 0x005A)
            || ($codepoint >= 0x0061 && $codepoint <= 0x007A)
            || ($codepoint >= 0x00C0 && $codepoint <= 0x024F)
            || ($codepoint >= 0x0660 && $codepoint <= 0x0669)
            || ($codepoint >= 0x06F0 && $codepoint <= 0x06F9);
    }
}
