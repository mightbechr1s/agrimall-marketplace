<?php
/** Guards that every copy block has identical keys in English and Tagalog. Run: php tests/i18n-parity.php */
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

// Second check: every referenced copy path must exist. Parity alone misses wrong paths
// because locale-first deck returns null for bad keys, which falls back to raw key text.
$missing  = [];
$checked  = 0;
$isLocale = static fn(string $k): bool => $k === 'en' || $k === 'tl';

$base = dirname(__DIR__);
$php  = [];
foreach (['public', 'src'] as $dir) {
    if (!is_dir("$base/$dir")) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$base/$dir"));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $php[] = $f->getPathname();
        }
    }
}

// Safe idiom: $l($copy['block'], 'key').
$callPattern = '/\$l\(\s*\$copy\[\s*\'([a-z0-9_]+)\'\s*\]\s*,\s*\'([a-z0-9_.]+)\'/i';
// Trap: two literal hops on locale-first deck where second hop is not a locale.
$pathPattern = '/\$copy\[\s*\'([a-z0-9_]+)\'\s*\]\s*\[\s*\'([a-z0-9_]+)\'\s*\]/i';

foreach ($php as $file) {
    $rel  = str_replace('\\', '/', substr($file, strlen($base) + 1));
    $code = (string) file_get_contents($file);

    preg_match_all($callPattern, $code, $hits, PREG_SET_ORDER);
    foreach ($hits as $hit) {
        [, $block, $key] = $hit;
        $checked++;
        if (!isset($copy[$block]['en']) || !is_array($copy[$block]['en'])) {
            $missing[] = "$rel: \$copy['$block'] is not a bilingual block";
            continue;
        }
        // A dotted key is only legal one level deep; walk it.
        $node = $copy[$block]['en'];
        foreach (explode('.', $key) as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) {
                $missing[] = "$rel: \$copy['$block']['$key'] does not exist (missing '$seg')";
                continue 2;
            }
            $node = $node[$seg];
        }
    }

    preg_match_all($pathPattern, $code, $hits2, PREG_SET_ORDER);
    foreach ($hits2 as $hit) {
        [, $block, $sub] = $hit;
        if ($isLocale($sub)) {
            continue; // $copy['errors']['en'] is the correct shape
        }
        $checked++;
        if (!isset($copy[$block][$sub]) || !is_array($copy[$block][$sub])) {
            $missing[] = "$rel: \$copy['$block']['$sub'] misses; the deck is locale-first, so this is null and will fall back to a raw key";
        }
    }
}

if ($checked === 0) {
    fwrite(STDERR, "FATAL: no copy references found to validate; the check would pass vacuously\n");
    exit(2);
}

echo "blocks checked: $blocks\n";
echo "copy references checked: $checked\n";

if ($problems === [] && $missing === []) {
    echo "OK: every block has identical en/tl key sets\n";
    echo "OK: every referenced copy path resolves\n";
    exit(0);
}
foreach ($problems as $p) {
    echo "  $p\n";
}
foreach ($missing as $m) {
    echo "  $m\n";
}
if ($problems !== []) {
    fwrite(STDERR, 'DRIFT: ' . count($problems) . " key(s) missing from one language\n");
}
if ($missing !== []) {
    fwrite(STDERR, 'BROKEN PATH: ' . count($missing) . " copy reference(s) do not resolve\n");
}
exit(1);
