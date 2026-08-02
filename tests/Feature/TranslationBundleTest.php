<?php

use App\Support\TranslationBundle;

/**
 * Vue reads its strings from the bundle Inertia ships, not from the PHP lang
 * files directly. A group missing from TranslationBundle::GROUPS therefore
 * renders as raw keys ("team.fields.first_name") in the browser while every
 * backend test still passes, so the coverage is asserted here.
 */
it('exposes every translation group referenced by the frontend', function () {
    $referenced = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'vue' && $file->getExtension() !== 'js') {
            continue;
        }

        // Matches $t('group.key') and t('group.key'), single or double quoted.
        preg_match_all(
            '/\$?\bt\(\s*[\'"]([a-z][a-z0-9_]*)\./i',
            (string) file_get_contents($file->getPathname()),
            $matches
        );

        foreach ($matches[1] as $group) {
            $referenced[$group][] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    $missing = array_diff(array_keys($referenced), TranslationBundle::GROUPS);

    expect($missing)->toBe([], 'Groups used in Vue but never sent to the client: '.implode(', ', $missing));
});

it('resolves the team strings in both locales', function (string $locale) {
    $bundle = TranslationBundle::forLocale($locale);

    expect($bundle)->toHaveKey('team')
        ->and($bundle['team']['fields']['first_name'])->toBeString()
        ->and($bundle['team']['sections']['identity'])->toBeString()
        ->and($bundle['team']['roles']['title'])->toBeString()
        ->and($bundle['user_statuses'])->toHaveKey('SUSPENDED')
        ->and($bundle['sidebar'])->toHaveKey('team');
})->with(['fr', 'en']);
