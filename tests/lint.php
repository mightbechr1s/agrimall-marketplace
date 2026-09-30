<?php
/**
 * Lints every project PHP file, not a hand-picked handful.
 *
 * The npm script used to name five files explicitly, so a syntax error in any
 * of the other ~55 files would reach the running app unnoticed. This walks the
 * source trees instead, so the file list cannot drift out of date.
 *
 * Run: php tests/lint.php
 */
declare(strict_types=1);

$repo   = dirname(__DIR__);
$roots  = ['public', 'src', 'config', 'database', 'tools', 'tests'];
$skip   = ['vendor', 'node_modules'];

$php = PHP_BINARY;
$bad = 0;
$seen = 0;

$files = [];
foreach ($roots as $root) {
    $dir = $repo . '/' . $root;
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        $path = $f->getPathname();
        foreach ($skip as $s) {
            if (str_contains(str_replace('\\', '/', $path), "/$s/")) {
                continue 2;
            }
        }
        if (strtolower($f->getExtension()) === 'php') {
            $files[] = $path;
        }
    }
}
sort($files);

foreach ($files as $path) {
    $seen++;
    $out = [];
    $code = 0;
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $bad++;
        echo '  ' . str_replace($repo . '/', '', $path) . "\n";
        foreach ($out as $line) {
            echo "      $line\n";
        }
    }
}

echo "linted: $seen file(s)\n";
if ($bad > 0) {
    fwrite(STDERR, "SYNTAX ERRORS in $bad file(s)\n");
    exit(1);
}
echo "OK: no syntax errors\n";
exit(0);
