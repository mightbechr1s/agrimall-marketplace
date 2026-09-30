<?php
/**
 * Guards that every copy block has the same keys in English and Tagalog.
 *
 * A key present in one language and missing in the other is the failure mode
 * that slips through review: the page still renders, but one language silently
 * falls back to English (or to a raw key) in production.
 *
 * Run: php tests/i18n-parity.php
 */
declare(strict_types=1);

$copyPath = dirname(__DIR__) . '/src/i18n_copy.php';
if (!is_file($copyPath)) {
    fwrite(STDERR, "FATAL: copy deck not found at $copyPath\n");
    exit(2);
}

$copy = include $copyPath;
if (!is_array($copy) || $copy === []) {
    // Guard against a vacuous pass: an unloadable deck must fail loudly, not
    // report every block as identical because nothing was compared.
    fwrite(STDERR, "FATAL: copy deck did not load\n");
    exit(2);
}

$problems = [];
$blocks   = 0;

$compare = function ($en, $tl, string $path) use (&$compare, &$problems): void {
    if (!is_array($en) || !is_array($tl)) {
        return; // leaf strings: key presence was already compared by the parent
    }
    foreach (array_diff(array_keys($en), array_keys($tl)) as $k) {
        $problems[] = "en-only  $path.$k";
    }
    foreach (array_diff(array_keys($tl), array_keys($en)) as $k) {
        $problems[] = "tl-only  $path.$k";
    }
    foreach ($en as $k => $v) {
        if (array_key_exists($k, $tl)) {
            $compare($v, $tl[$k], "$path.$k");
        }
    }
};

foreach ($copy as $block => $langs) {
    if (is_array($langs) && isset($langs['en'], $langs['tl'])) {
        $blocks++;
        $compare($langs['en'], $langs['tl'], (string) $block);
    }
}

if ($blocks === 0) {
    fwrite(STDERR, "FATAL: no bilingual blocks found; the check would pass vacuously\n");
    exit(2);
}

echo "blocks checked: $blocks\n";
if ($problems === []) {
    echo "OK: every block has identical en/tl key sets\n";
    exit(0);
}
foreach ($problems as $p) {
    echo "  $p\n";
}
fwrite(STDERR, 'DRIFT: ' . count($problems) . " key(s) missing from one language\n");
exit(1);
