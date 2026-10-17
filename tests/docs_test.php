<?php
/**
 * Keeps docs/decisions.md honest about the numbers that are code, not data.
 *
 * The capacity ceilings in that document (page sizes, export and dropdown caps,
 * sitemap chunking) are claims about constants in the source. If someone changes
 * a constant, the document becomes wrong and nothing else notices.
 *
 * Deliberately does NOT check the document's "current scale" sentence (listings,
 * users, orders): those change as the database is used, so asserting them would
 * produce a failing test for a perfectly healthy reason. Those figures were
 * verified once when written; this file guards only what cannot drift.
 *
 * Run: php tests/docs_test.php
 */
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$docPath = $repoRoot . '/docs/decisions.md';
if (!is_file($docPath)) {
    fwrite(STDERR, "FATAL: docs/decisions.md not found\n");
    exit(2);
}
$doc = (string) file_get_contents($docPath);

$pass = 0;
$fail = [];
$check = function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        return;
    }
    $fail[] = $label . ($detail !== '' ? "  <- $detail" : '');
};

/** Read a PHP constant's literal value from a source file. */
$const = function (string $file, string $name): ?string {
    $src = (string) file_get_contents($file);
    return preg_match('/const\s+' . preg_quote($name, '/') . '\s*=\s*([0-9]+)\s*;/', $src, $m) ? $m[1] : null;
};

/** The number the document states in its capacity table for a given row label. */
$docCell = function (string $rowLabel) use ($doc): ?string {
    // | Sitemap URLs per file | 50,000 | ...
    if (!preg_match('/^\|\s*' . preg_quote($rowLabel, '/') . '\s*\|\s*([\d,]+)\s*\|/m', $doc, $m)) {
        return null;
    }
    return str_replace(',', '', $m[1]);
};

$dash = $repoRoot . '/public/dashboard.php';
$reports = $repoRoot . '/public/admin_reports.php';
$sitemap = $repoRoot . '/public/sitemap.php';

echo "docs/decisions.md: stated ceilings match the code\n";

// ---- D-01 pagination ------------------------------------------------------
$pageSizes = [
    'Listings per page (My Listings)' => [$dash, '$listingsPerPage = ', 12],
    'Saved listings per page' => [$dash, '$savedPerPage = ', 12],
    'Order history per page (farmer/buyer)' => [$dash, '$ordersPerPage = ', 15],
];
foreach ($pageSizes as $row => [$file, $needle, $expected]) {
    $src = (string) file_get_contents($file);
    preg_match('/' . preg_quote($needle, '/') . '(\d+)/', $src, $m);
    $code = $m[1] ?? null;
    $check("code $needle is $expected", $code === (string) $expected, "got " . var_export($code, true));
    $check("decisions.md row '$row' says $expected", $docCell($row) === (string) $expected, 'row missing or stale');
}

// ---- D-02 sitemap ---------------------------------------------------------
$maxUrls = $const($sitemap, 'SITEMAP_MAX_URLS');
$check('SITEMAP_MAX_URLS is 50000', $maxUrls === '50000', var_export($maxUrls, true));
$check('decisions.md sitemap row says 50000', $docCell('Sitemap URLs per file') === '50000');

$router = (string) file_get_contents($repoRoot . '/public/router.php');
preg_match('/range\(1,\s*(\d+)\)/', $router, $m);
$maxFiles = $m[1] ?? null;
$check('router maps 50 sitemap chunks', $maxFiles === '50', var_export($maxFiles, true));
$check('decisions.md sitemap-files row says 50', $docCell('Sitemap files') === '50');

// The prose ceiling must equal files x URLs, not merely sound plausible.
if (preg_match('/([\d.]+) millionth listing/', $doc, $m)) {
    $stated = (int) ((float) $m[1] * 1_000_000);
    $real = (int) $maxFiles * (int) $maxUrls;
    $check("prose ceiling $stated equals $maxFiles files x $maxUrls URLs", $stated === $real, "arithmetic mismatch");
} else {
    $fail[] = 'the sitemap ceiling sentence is missing from decisions.md';
}

// ---- D-03 / D-04 report caps ---------------------------------------------
$exportCap = $const($reports, 'REPORT_EXPORT_MAX_ROWS');
$check('REPORT_EXPORT_MAX_ROWS is 2000', $exportCap === '2000', var_export($exportCap, true));
$check('decisions.md export row says 2000', $docCell('Report export rows') === '2000');

$filterCap = $const($reports, 'REPORT_FILTER_MAX_OPTIONS');
$check('REPORT_FILTER_MAX_OPTIONS is 100', $filterCap === '100', var_export($filterCap, true));
$check('decisions.md filter row says 100', $docCell('Report filter options') === '100');

// ---- truncation disclosure must stay coupled to the cap ------------------
// If the note were removed, the cap would truncate silently, which is the exact
// failure D-03 exists to prevent.
$reportsSrc = (string) file_get_contents($reports);
$check(
    'export truncation is still disclosed in the PDF',
    str_contains($reportsSrc, 'Showing the first') && str_contains($reportsSrc, 'Narrow the filters'),
    'the disclosure note is gone: the cap would truncate silently'
);

// ---- the document must not be an orphan ----------------------------------
$srs = (string) file_get_contents($repoRoot . '/docs/SRS.md');
$check('decisions.md does not restate requirements the matrix already owns', str_contains($doc, 'traceability-matrix.md'));

// ---- decisions.md must not quote stale coverage figures ------------------
// The coverage paragraph in decisions.md restates the traceability matrix. Those
// numbers change only when traceability.json is deliberately edited, so they are
// safe to assert -- and leaving them unchecked is how they went stale once
// already, quietly claiming 45 automated when the matrix said 49.
$map = json_decode((string) file_get_contents($repoRoot . '/tests/traceability.json'), true);
if (is_array($map)) {
    $counts = array_count_values(array_column($map, 'status'));
    $docNumbers = [];
    if (preg_match(
        '/records (\d+) requirements: (\d+) automated, (\d+) partial, (\d+) verified by inspection,\s*and \*\*(\d+) gaps\*\*/',
        $doc,
        $m
    )) {
        $docNumbers = ['total' => $m[1], 'automated' => $m[2], 'partial' => $m[3], 'manual' => $m[4], 'gap' => $m[5]];
    }
    $check('decisions.md states the coverage figures', $docNumbers !== [], 'the coverage paragraph was not found');
    $expected = [
        'total' => (string) count($map),
        'automated' => (string) ($counts['automated'] ?? 0),
        'partial' => (string) ($counts['partial'] ?? 0),
        'manual' => (string) ($counts['manual'] ?? 0),
        'gap' => (string) ($counts['gap'] ?? 0),
    ];
    foreach ($expected as $key => $value) {
        $check(
            "decisions.md $key matches traceability.json ($value)",
            ($docNumbers[$key] ?? null) === $value,
            'document says ' . var_export($docNumbers[$key] ?? null, true)
        );
    }
} else {
    $check('tests/traceability.json parses', false, 'could not decode');
}

echo "\nassertions passed: $pass\n";
echo 'assertions failed: ' . count($fail) . "\n";

if ($fail === []) {
    echo "OK: every ceiling in decisions.md matches the code\n";
    exit(0);
}
foreach ($fail as $f) {
    echo "  $f\n";
}
fwrite(STDERR, 'DOC/CODE DRIFT: ' . count($fail) . "\n");
exit(1);