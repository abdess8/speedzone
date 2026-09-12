<?php

use App\Enums\YouCanImportStatus;
use App\Services\Ecommerce\YouCan\YouCanOrderMapper;

it('normalizes Moroccan mobile numbers into the 0XXXXXXXXX form', function (string $input, ?string $expected) {
    $mapper = new YouCanOrderMapper;

    expect($mapper->normalizePhone($input))->toBe($expected);
})->with([
    'local' => ['0612345678', '0612345678'],
    'plus 212' => ['+212612345678', '0612345678'],
    '212 prefix' => ['212612345678', '0612345678'],
    'nine digits' => ['612345678', '0612345678'],
    'spaces' => ['06 12 34 56 78', '0612345678'],
    'hyphenated' => ['0660-033231', '0660033231'],
    'plus 212 hyphen' => ['+212675068285', '0675068285'],
    'zero zero 212' => ['00212612345678', '0612345678'],
    'too short' => ['061234567', null],
    'letters' => ['06ABCDEFGH', null],
]);

it('reads the phone from the Téléphone extra field rather than Nom Complet', function () {
    $mapper = new YouCanOrderMapper;
    $payload = [
        'extra_fields' => [
            ['name' => 'Nom Complet', 'value' => 'Sara Benali'],
            ['name' => 'Téléphone', 'value' => '0612345678'],
            ['name' => 'Ville', 'value' => 'Casablanca'],
            ['name' => 'Adresse', 'value' => '12 rue Maarif'],
        ],
    ];

    $customer = (new ReflectionClass($mapper))->getMethod('customer');
    $customer->setAccessible(true);
    $parsed = $customer->invoke($mapper, $payload);

    expect($parsed['phone'])->toBe('0612345678')
        ->and($parsed['first_name'])->toBe('Sara')
        ->and($parsed['city'])->toBe('Casablanca');
});

it('reads bilingual YouCan extra-field maps', function () {
    $mapper = new YouCanOrderMapper;
    $payload = [
        'extra_fields' => [
            'Nom Complet / الاسم الكامل' => 'Sara Benali',
            'Téléphone / الهاتف' => '0612345678',
            'Ville / مدينتك' => 'Casablanca',
            'Votre Adresse / عنوانك' => '12 rue Maarif',
        ],
    ];

    $customer = (new ReflectionClass($mapper))->getMethod('customer');
    $customer->setAccessible(true);
    $parsed = $customer->invoke($mapper, $payload);

    expect($parsed['phone'])->toBe('0612345678')
        ->and($parsed['first_name'])->toBe('Sara')
        ->and($parsed['city'])->toBe('Casablanca')
        ->and($parsed['address'])->toBe('12 rue Maarif');
});

it('reads the native YouCan customer when extra fields only have the address', function () {
    $mapper = new YouCanOrderMapper;
    $payload = [
        'extra_fields' => [
            'Votre Adresse / عنوانك' => 'Centre ville',
        ],
        'customer' => [
            'first_name' => 'Wissame',
            'last_name' => '',
            'full_name' => 'Wissame ',
            'phone' => '0660-033231',
            'city' => 'Kenitra',
        ],
    ];

    $customer = (new ReflectionClass($mapper))->getMethod('customer');
    $customer->setAccessible(true);
    $parsed = $customer->invoke($mapper, $payload);

    expect($parsed['phone'])->toBe('0660-033231')
        ->and($mapper->normalizePhone($parsed['phone']))->toBe('0660033231')
        ->and($parsed['first_name'])->toBe('Wissame')
        ->and($parsed['city'])->toBe('Kenitra')
        ->and($parsed['address'])->toBe('Centre ville');
});

it('matches YouCan order slugs and payment slugs separately', function () {
    $mapper = new YouCanOrderMapper;
    $payload = [
        'status_new' => ['slug' => 'open'],
        'paymentStatus' => ['slug' => 'unpaid'],
    ];

    expect($mapper->matchesImportStatus($payload, YouCanImportStatus::Open))->toBeTrue()
        ->and($mapper->matchesImportStatus($payload, YouCanImportStatus::Paid))->toBeFalse()
        ->and($mapper->matchesImportStatus($payload, YouCanImportStatus::Unpaid))->toBeTrue();
});

it('reads the shop-facing YouCan order reference', function () {
    $mapper = new YouCanOrderMapper;

    expect($mapper->orderRef(['ref' => 'YC-1001']))->toBe('YC-1001')
        ->and($mapper->orderRef(['reference' => ' SO-42 ']))->toBe('SO-42')
        ->and($mapper->orderRef(['id' => 'order-uuid-1']))->toBeNull();
});
