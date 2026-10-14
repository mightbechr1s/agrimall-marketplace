<?php
/** Checks hand-built report PDF: structure, pagination, escaping. Run: php tests/pdf_test.php */
declare(strict_types=1);

$functionsPath = dirname(__DIR__) . '/src/functions.php';
if (!is_file($functionsPath)) {
    fwrite(STDERR, "FATAL: helpers not found at $functionsPath\n");
    exit(2);
}
require_once $functionsPath;

if (!function_exists('build_report_pdf')) {
    fwrite(STDERR, "FATAL: build_report_pdf() is not defined -- src/functions.php did not declare it\n");
    exit(2);
}

$pass = 0;
$fail = [];

$ok = function (string $label, bool $condition, string $detail = '') use (&$pass, &$fail): void {
    if ($condition) {
        $pass++;
        return;
    }
    $fail[] = $detail === '' ? $label : sprintf('%-46s %s', $label, $detail);
};

// Pull content streams using /Length so "endstream" in content doesn't truncate capture.
$streams = static function (string $pdf): array {
    $found = [];
    if (preg_match_all('/<< \/Length (\d+) >>\nstream\n/', $pdf, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $i => $hit) {
            $declared = (int) $m[1][$i][0];
            $start = $hit[1] + strlen($hit[0]);
            $found[] = ['declared' => $declared, 'body' => substr($pdf, $start, $declared)];
        }
    }
    return $found;
};

// Text on a page, with PDF literal-string escaping undone.
$page_text = static function (string $content): array {
    $text = [];
    if (preg_match_all('/^\((.*)\) Tj$/m', $content, $m)) {
        foreach ($m[1] as $literal) {
            $text[] = preg_replace_callback(
                '/\\\\(.)/',
                static fn (array $c): string => $c[1],
                $literal
            );
        }
    }
    return $text;
};

// structure of a small export
$small = build_report_pdf('Sales report', 'sales', ['From: 2026-01-01'], [
    ['order_number' => 'ORD-1', 'total' => '1250.00'],
], ['orders' => 1, 'revenue' => 1250.0]);

$ok('starts with %PDF-1.4', str_starts_with($small, '%PDF-1.4'), substr($small, 0, 12));
$ok('ends with %%EOF', str_ends_with($small, "%%EOF"));

preg_match('/<< \/Type \/Pages \/Kids \[(.*?)\] \/Count (\d+) >>/s', $small, $pagesMatch);
$declaredCount = (int) ($pagesMatch[2] ?? 0);
preg_match_all('/(\d+) 0 R/', $pagesMatch[1] ?? '', $kidMatches);
$kids = $kidMatches[1] ?? [];

$pageObjects = preg_match_all('/<< \/Type \/Page /', $small);
$ok('/Count matches the number of page objects', $declaredCount === $pageObjects, "Count=$declaredCount pages=$pageObjects");
$ok('/Kids lists one ref per page', count($kids) === $pageObjects, 'kids=' . count($kids) . " pages=$pageObjects");

foreach ($kids as $kid) {
    $ref = (int) $kid;
    $ok('Kid ' . $kid . ' resolves to a page object', str_contains($small, "\n$ref 0 obj\n<< /Type /Page "));
}

// xref offsets point at claimed objects
preg_match_all('/^(\d{10}) 00000 n $/m', $small, $xrefMatches, PREG_SET_ORDER);
$ok('one xref entry per object', count($xrefMatches) === 3 + 2 * $pageObjects, 'entries=' . count($xrefMatches));
foreach ($xrefMatches as $i => $entry) {
    $offset = (int) $entry[1];
    $objectNumber = $i + 1;
    $at = substr($small, $offset, strlen("$objectNumber 0 obj"));
    $ok("xref entry $objectNumber points at its object", $at === "$objectNumber 0 obj", "found '$at' at $offset");
}

// declared stream lengths match bytes
$smallStreams = $streams($small);
$ok('found a content stream per page', count($smallStreams) === $pageObjects);
foreach ($smallStreams as $i => $stream) {
    $ok("page " . ($i + 1) . " stream /Length is accurate", $stream['declared'] === strlen($stream['body']),
        'declared=' . $stream['declared'] . ' actual=' . strlen($stream['body']));
}

// pagination fits on the page
$rows = [];
for ($i = 1; $i <= 300; $i++) {
    $rows[] = ['order_number' => sprintf('ORD-%04d', $i), 'buyer' => 'Buyer ' . $i, 'total' => number_format(125 + $i, 2)];
}
$big = build_report_pdf('Sales report', 'sales', ['From: 2026-01-01', 'To: 2026-12-31'], $rows, ['orders' => 300, 'revenue' => 45600.0]);

$bigStreams = $streams($big);
$ok('300 rows span multiple pages', count($bigStreams) > 1, 'pages=' . count($bigStreams));

$bottoms = [];
foreach ($bigStreams as $i => $stream) {
    $body = $stream['body'];
    preg_match('/^40 (\d+) Td$/m', $body, $startMatch);
    preg_match_all('/^0 -(\d+) Td$/m', $body, $leadMatches);
    $top = (float) ($startMatch[1] ?? 0);
    $leading = (float) ($leadMatches[1][0] ?? 0);
    $drawn = substr_count($body, ') Tj');
    if ($drawn < 1) {
        $fail[] = sprintf('page %d drew no text', $i + 1);
        continue;
    }
    $bottoms[] = $top - $leading * ($drawn - 1);
    $ok('page ' . ($i + 1) . ' last baseline clears the bottom margin', ($top - $leading * ($drawn - 1)) > 40,
        'last baseline=' . ($top - $leading * ($drawn - 1)));
    $ok('page ' . ($i + 1) . ' starts inside the top margin', $top <= 800 && $top > 40, "top=$top");
}
$ok('no page overflows', $bottoms !== [] && min($bottoms) > 40, 'lowest baseline=' . ($bottoms ? min($bottoms) : 'n/a'));

// no rows lost across page breaks
$seen = '';
foreach ($bigStreams as $stream) {
    $seen .= implode("\n", $page_text($stream['body'])) . "\n";
}
$missing = 0;
for ($i = 1; $i <= 300; $i++) {
    if (!str_contains($seen, sprintf('ORD-%04d', $i))) {
        $missing++;
    }
}
$ok('every row appears in the export', $missing === 0, "missing=$missing");

// escaping round-trips
$tricky = build_report_pdf('Sales report', 'sales', [], [
    ['note' => 'cancelled (duplicate) by (admin)'],
    ['note' => 'path C:\\temp\\x and (unbalanced'],
], null);
$trickyText = implode("\n", array_map(
    static fn (array $s): string => implode("\n", $page_text($s['body'])),
    $streams($tricky)
));
$ok('parentheses survive escaping', str_contains($trickyText, 'cancelled (duplicate) by (admin)'));
$ok('backslashes survive escaping', str_contains($trickyText, 'path C:\\temp\\x and (unbalanced'));
$ok('unbalanced parens do not truncate the stream', str_contains($trickyText, '(No rows matched.)') === false
    && str_contains($trickyText, 'note=path C:\\temp\\x and (unbalanced'));

// empty export still produces a valid file
$empty = build_report_pdf('Cancellations', 'cancellations', ['none'], [], null);
$emptyStreams = $streams($empty);
$ok('empty export has one page', count($emptyStreams) === 1, 'pages=' . count($emptyStreams));
$ok('empty export says so', str_contains(implode('', $page_text($emptyStreams[0]['body'])), '(No rows matched.)'));
$ok('empty export is still a PDF', str_starts_with($empty, '%PDF-1.4') && str_ends_with($empty, '%%EOF'));

// report
foreach ($fail as $line) {
    echo "  FAIL  $line\n";
}
printf("  %d passed, %d failed\n", $pass, count($fail));
exit($fail ? 1 : 0);
