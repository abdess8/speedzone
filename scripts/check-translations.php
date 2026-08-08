<?php

/**
 * Reports i18n keys referenced by the Vue layer that no language file defines.
 *
 * A missing key renders as the key itself, which is how `common.settings` ended
 * up printed on the alerts page. Only literal keys are checked — anything built
 * at runtime (`roles.${name}`) is skipped and listed separately, because a
 * static scan cannot know what it resolves to.
 *
 * Usage: php scripts/check-translations.php [locale]
 */

$root = dirname(__DIR__);
$locale = $argv[1] ?? 'fr';
$langDir = "{$root}/resources/lang/{$locale}";

if (! is_dir($langDir)) {
    fwrite(STDERR, "No language directory for '{$locale}'.\n");
    exit(1);
}

$messages = [];

foreach (glob("{$langDir}/*.php") as $file) {
    $messages[basename($file, '.php')] = require $file;
}

/** Whether a dotted key resolves to a leaf in the loaded messages. */
$resolves = static function (string $key) use ($messages): bool {
    $segments = explode('.', $key);
    $node = $messages;

    foreach ($segments as $segment) {
        if (! is_array($node) || ! array_key_exists($segment, $node)) {
            return false;
        }

        $node = $node[$segment];
    }

    return ! is_array($node);
};

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator("{$root}/resources/js", FilesystemIterator::SKIP_DOTS)
);

$missing = [];
$dynamic = 0;

foreach ($files as $file) {
    if (! in_array($file->getExtension(), ['vue', 'js'], true)) {
        continue;
    }

    $source = file_get_contents($file->getPathname());

    // $t('group.key') / t("group.key") / $tc(...) with a literal argument.
    preg_match_all('/\$?tc?\(\s*([\'"])([a-z0-9_]+(?:\.[a-z0-9_]+)+)\1/i', $source, $literal);
    $dynamic += preg_match_all('/\$?tc?\(\s*`/', $source);

    foreach ($literal[2] as $key) {
        if (! $resolves($key)) {
            $missing[$key][] = str_replace("{$root}/", '', $file->getPathname());
        }
    }
}

ksort($missing);

foreach ($missing as $key => $usedIn) {
    printf("%-55s %s\n", $key, implode(', ', array_unique($usedIn)));
}

printf(
    "\n%d missing key(s) in '%s'. %d template-literal call(s) skipped.\n",
    count($missing),
    $locale,
    $dynamic
);

exit($missing === [] ? 0 : 1);
