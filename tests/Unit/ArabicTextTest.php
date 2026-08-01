<?php

use App\Support\ArabicText;

it('leaves latin text untouched', function () {
    expect(ArabicText::render('Résidence Al Amal, Casablanca'))
        ->toBe('Résidence Al Amal, Casablanca');

    expect(ArabicText::isRtl('Casablanca'))->toBeFalse();
    expect(ArabicText::cssClass('Casablanca'))->toBe('');
});

it('handles empty and null values', function () {
    expect(ArabicText::render(null))->toBe('');
    expect(ArabicText::render(''))->toBe('');
    expect(ArabicText::isRtl(null))->toBeFalse();
});

it('shapes an isolated arabic letter as isolated', function () {
    // Single beh has no neighbour: isolated form U+FE8F.
    expect(ArabicText::render('ب'))->toBe("\u{FE8F}");
});

it('shapes a word with initial, medial and final forms', function () {
    // بحر : beh initial, hah medial, reh final. Emitted right to left.
    expect(ArabicText::render('بحر'))
        ->toBe("\u{FEAE}\u{FEA4}\u{FE91}");
});

it('does not connect a right-joining letter to what follows it', function () {
    // دار : dal never joins forward, so alef and reh both stay isolated.
    expect(ArabicText::render('دار'))
        ->toBe("\u{FEAD}\u{FE8D}\u{FEA9}");
});

it('fuses lam followed by alef into the mandatory ligature', function () {
    // لا alone is the isolated lam-alef ligature U+FEFB.
    expect(ArabicText::render('لا'))->toBe("\u{FEFB}");

    // بلا : the ligature now connects to the beh before it, hence the final form.
    expect(ArabicText::render('بلا'))->toBe("\u{FEFC}\u{FE91}");
});

it('drops vowel marks that dompdf cannot position', function () {
    expect(ArabicText::render('بَحْر'))->toBe(ArabicText::render('بحر'));
});

it('keeps embedded numbers left to right', function () {
    $rendered = ArabicText::render('دار 2025');

    // The number keeps its own order and lands on the left of the arabic run.
    expect($rendered)->toStartWith('2025 ');
    expect($rendered)->toEndWith("\u{FEAD}\u{FE8D}\u{FEA9}");
});

it('keeps embedded latin words readable', function () {
    $rendered = ArabicText::render('دار Casa');

    expect($rendered)->toStartWith('Casa ');
});

it('mirrors brackets inside a right-to-left run', function () {
    expect(ArabicText::render('(بحر)'))
        ->toBe("(\u{FEAE}\u{FEA4}\u{FE91})");
});

it('flags arabic values as right to left', function () {
    expect(ArabicText::isRtl('محمد'))->toBeTrue();
    expect(ArabicText::cssClass('محمد'))->toBe('rtl');
});

it('leaves short values and latin values on a single line', function () {
    expect(ArabicText::lines('12 rue de la Liberté, Casablanca', 10))
        ->toBe(['12 rue de la Liberté, Casablanca']);

    expect(ArabicText::lines('دار', 10))->toBe([ArabicText::render('دار')]);
});

it('breaks a long arabic value into lines that stay in reading order', function () {
    $lines = ArabicText::lines('شارع محمد الخامس إقامة الأمل رقم 12', 18);

    expect($lines)->toHaveCount(2)
        // The first line holds the beginning of the address, not its end.
        ->and($lines[0])->toBe(ArabicText::render('شارع محمد الخامس'))
        ->and($lines[1])->toBe(ArabicText::render('إقامة الأمل رقم 12'));
});

it('never drops a word that is longer than the requested line', function () {
    $lines = ArabicText::lines('استقبالهم الاستثنائي جدا', 5);

    expect($lines)->toHaveCount(3);
});

it('renders a realistic moroccan address', function () {
    $rendered = ArabicText::render('شارع محمد الخامس، رقم 12، الدار البيضاء');

    // Nothing from the base arabic block survives shaping, and the whole
    // string is made of presentation forms, digits, spaces and punctuation.
    expect($rendered)->not->toMatch('/[\x{0621}-\x{064A}]/u');
    expect($rendered)->toContain('12');
});
