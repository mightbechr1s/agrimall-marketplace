<?php
/**
 * Traceability matrix generator and validator.
 *
 * Reads docs/SRS.md as the requirement source of truth, reads
 * tests/traceability.json as the requirement -> evidence map, then verifies
 * that every piece of evidence actually exists in the named test suite before
 * emitting docs/traceability-matrix.md.
 *
 * A stale map is a worse map than no map, so an unknown suite or an assertion
 * label that is not present in the suite fails the build.
 *
 * Usage:
 *   php tools/gen_traceability.php --labels   list discovered assertion labels
 *   php tools/gen_traceability.php           validate and write the matrix
 */
declare(strict_types=1);

const REPO = __DIR__ . '/..';

const SUITES = [
    'http'   => ['path' => 'tests/http_test.ps1',   're' => ["/(?ms)^\s*ok\s*(?:\(\s*)?'((?:[^']|'')*)'/", '/(?ms)^\s*ok\s*(?:\(\s*)?"([^"]*)"/']],
    'panels' => ['path' => 'tests/panels_test.ps1', 're' => ["/(?ms)^\s*ok\s*(?:\(\s*)?'((?:[^']|'')*)'/", '/(?ms)^\s*ok\s*(?:\(\s*)?"([^"]*)"/']],
    'e2e'    => ['path' => 'tests/e2e/smoke.js',    're' => ["/(?ms)\bok\s*\(\s*'((?:[^']|'')*)'/"]],
    'valid'  => ['path' => 'tests/validation.php',  're' => ["/(?ms)\\\$check\s*\(\s*'((?:[^']|'')*)'/"]],
    'pdf'    => ['path' => 'tests/pdf_test.php',    're' => ["/(?ms)\\\$ok\s*\(\s*'((?:[^']|'')*)'/"]],
];

/** Dispositions other than "automated" must carry a short note in
 *  tests/traceability.json explaining what a reviewer can inspect instead, or
 *  why nothing is claimed. A status with no reason is not honest. */
const NON_AUTOMATED = ['partial', 'manual', 'gap'];

function read_file(string $rel): string
{
    $abs = REPO . '/' . $rel;
    if (!is_readable($abs)) {
        fwrite(STDERR, "missing file: $rel\n");
        exit(1);
    }
    return (string) file_get_contents($abs);
}

/** Collapse PowerShell interpolation so a loop-generated label still maps to a
 *  stable key: "$role sidebar shows '$label'" becomes "* sidebar shows '*'". */
function normalize_label(string $label): string
{
    $label = preg_replace('/\$\([^)]*\)/', '*', $label);
    $label = preg_replace('/\$\{[^}]*\}/', '*', $label);
    $label = preg_replace('/\$[A-Za-z_][A-Za-z0-9_.]*/', '*', $label);
    return preg_replace('/\s+/', ' ', $label);
}

/** @return array<string, array<string, array{file:string, sites:int}>> */
function discover_labels(): array
{
    $out = [];
    foreach (SUITES as $suite => $meta) {
        $src = read_file($meta['path']);
        $out[$suite] = [];
        foreach ((array) $meta['re'] as $re) {
            if (!preg_match_all($re, $src, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[1] as $hit) {
                [$raw, $offset] = $hit;
                $label = normalize_label(str_replace("''", "'", $raw));
                $line  = substr_count(substr($src, 0, $offset), "\n") + 1;
                if (isset($out[$suite][$label])) {
                    $out[$suite][$label]['sites']++;
                    $out[$suite][$label]['lines'][] = $line;
                } else {
                    $out[$suite][$label] = [
                        'file'  => $meta['path'],
                        'sites' => 1,
                        'lines' => [$line],
                    ];
                }
            }
        }
    }
    return $out;
}

/** @return array<int, array{id:string,kind:string,module:string,text:string}> */
function parse_srs(): array
{
    $md  = read_file('docs/SRS.md');
    $reqs = [];
    if (preg_match_all('/\*\*((?:FR|NFR)-[A-Z]+-\d+)\s*:\*\*\s*(.+?)(?=\r?\n|$)/u', $md, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $id  = $hit[1];
            $txt = trim(preg_replace('/\s+/u', ' ', $hit[2]) ?? '');
            $parts = explode('-', $id, 3);
            $reqs[] = [
                'id'     => $id,
                'kind'   => $parts[0],
                'module' => $parts[1] ?? '',
                'text'   => rtrim($txt, '.'),
            ];
        }
    }
    return $reqs;
}

$labels = discover_labels();
$reqs   = parse_srs();

if (in_array('--labels', $argv, true)) {
    foreach ($labels as $suite => $set) {
        echo "== $suite (" . SUITES[$suite]['path'] . "): " . count($set) . " labels\n";
        foreach (array_keys($set) as $i => $label) {
            echo '   ' . ($i + 1) . ". $label\n";
        }
    }
    echo "\n== requirements parsed: " . count($reqs) . "\n";
    exit(0);
}

// ---- validate the map -------------------------------------------------------
$mapPath = REPO . '/tests/traceability.json';
if (!is_readable($mapPath)) {
    fwrite(STDERR, "missing tests/traceability.json - build the map first\n");
    exit(1);
}
$map = json_decode((string) file_get_contents($mapPath), true);
if (!is_array($map)) {
    fwrite(STDERR, "tests/traceability.json is not valid JSON\n");
    exit(1);
}

$errors  = [];
$reqIds  = array_column($reqs, 'id');
$reqById = array_column($reqs, null, 'id');
$usedLabels = [];

foreach ($map as $id => $entry) {
    if (!in_array($id, $reqIds, true)) {
        $errors[] = "$id is not a requirement in docs/SRS.md";
        continue;
    }
    $status = $entry['status'] ?? '';
    if (!in_array($status, ['automated', 'partial', 'manual', 'gap'], true)) {
        $errors[] = "$id has an unknown status '" . $status . "'";
    }
    foreach ((array) ($entry['evidence'] ?? []) as $ev) {
        if (!preg_match('/^([a-z0-9]+)::(.+)$/', $ev, $m2)) {
            $errors[] = "$id evidence '$ev' must look like suite::assertion-label";
            continue;
        }
        [$all, $suite, $label] = $m2;
        if (!isset(SUITES[$suite])) {
            $errors[] = "$id evidence '$ev' names an unknown suite '$suite'";
            continue;
        }
        if (!isset($labels[$suite][$label])) {
            $errors[] = "$id evidence '$ev' does not match any assertion in " . SUITES[$suite]['path'];
            continue;
        }
        $usedLabels[$suite][$label][] = $id;
    }
    if (in_array($status, NON_AUTOMATED, true) && empty(trim((string) ($entry['note'] ?? '')))) {
        $errors[] = "$id is '$status' but has no note explaining what a reviewer can inspect";
    }
    if ($status === 'automated' && empty($entry['evidence'])) {
        $errors[] = "$id is 'automated' but lists no evidence";
    }
}

// reverse coverage: every discovered assertion must trace back to a requirement
foreach ($labels as $suite => $set) {
    foreach ($set as $label => $info) {
        if (empty($usedLabels[$suite][$label])) {
            $errors[] = "assertion not mapped to any requirement: $suite::\"$label\" ({$info['file']})";
        }
    }
}

if ($errors) {
    fwrite(STDERR, "traceability map is inconsistent:\n");
    foreach ($errors as $e) { fwrite(STDERR, "  - $e\n"); }
    exit(1);
}

// ---- emit -------------------------------------------------------------------
$byStatus = ['automated' => [], 'partial' => [], 'manual' => [], 'gap' => []];
foreach ($map as $id => $entry) { $byStatus[$entry['status']][] = $id; }

$totals = count($reqs);
$auto   = count($byStatus['automated']);
$part   = count($byStatus['partial']);

$out = [];
$out[] = '# AniMAL Requirements Traceability Matrix';
$out[] = '';
$out[] = '> Generated file. Do not edit by hand. Source of truth for requirements is';
$out[] = '> `docs/SRS.md`; source of truth for evidence mapping is';
$out[] = '> `tests/traceability.json`. Regenerate with `php tools/gen_traceability.php`.';
$out[] = '>';
$out[] = '> The generator refuses to run if a mapped assertion does not exist in the suite it';
$out[] = '> names, if a requirement is not in the SRS, or if an assertion is mapped to nothing.';
$out[] = '';
$out[] = '## Coverage summary';
$out[] = '';
$out[] = '| Disposition | Requirements | Share |';
$out[] = '|---|---:|---:|';
$out[] = "| Automated | $auto | " . ($totals ? round(100 * $auto / $totals, 1) : 0) . "% |";
$out[] = "| Partial | $part | " . ($totals ? round(100 * $part / $totals, 1) : 0) . "% |";
$out[] = '| Manual / inspection | ' . count($byStatus['manual']) . ' | ' . ($totals ? round(100 * count($byStatus['manual']) / $totals, 1) : 0) . "% |";
$out[] = "| Not verified (gap) | " . count($byStatus['gap']) . ' | ' . ($totals ? round(100 * count($byStatus['gap']) / $totals, 1) : 0) . "% |";
$out[] = "| **Total** | **$totals** | 100% |";
$out[] = '';
$out[] = 'Automated means at least one executable assertion verifies the requirement.';
$out[] = 'Partial means some of it is automated. Manual means verified by inspection of a';
$out[] = 'named file. Gap means it is not implemented or not verified - these are listed';
$out[] = 'openly rather than hidden.';
$out[] = '';

$modules = [];
foreach ($reqs as $r) { $modules[$r['module']][] = $r['id']; }
ksort($modules);

foreach ($modules as $mod => $ids) {
    $out[] = "## Module $mod";
    $out[] = '';
    $out[] = '| Requirement | Disposition | Evidence |';
    $out[] = '|---|---|---|';
    foreach ($ids as $id) {
        $entry = $map[$id] ?? ['status' => 'gap', 'evidence' => []];
        $disp  = $entry['status'];
        $note  = (string) ($entry['note'] ?? '');
        $evs   = (array) ($entry['evidence'] ?? []);
        $cells = [];
        foreach ($evs as $ev) {
            [$suite, $label] = explode('::', $ev, 2);
            $short = $label;
            if (strlen($short) > 46) { $short = substr($short, 0, 43) . '...'; }
            $ref = $labels[$suite][$label]['file'] . ':'
                . implode(',', array_unique($labels[$suite][$label]['lines']));
            $cells[] = "`$suite` `$ref` — $short";
        }
        if (!$cells) {
            $cells[] = $note !== '' ? $note : '—';
        } elseif ($note !== '') {
            $cells[] = 'Also: ' . $note;
        }
        $reqText = $reqById[$id]['text'] ?? $id;
        if (strlen($reqText) > 96) { $reqText = substr($reqText, 0, 93) . '...'; }
        $out[] = '| `' . $id . '` ' . $reqText . ' | ' . $disp . ' | ' . implode('<br>', $cells) . ' |';
    }
    $out[] = '';
}

$out[] = '## Assertion inventory';
$out[] = '';
$out[] = 'Call sites are counted statically, one per source line. Suites that run an'
    . ' assertion inside a loop report a higher pass count at runtime than the static'
    . ' number here.';
$out[] = '';
$out[] = '| Suite | File | Distinct labels | Static call sites | Requirements reached |';
$out[] = '|---|---|---:|---:|---:|';
foreach (SUITES as $suite => $meta) {
    $sites = 0;
    foreach ($labels[$suite] as $info) { $sites += $info['sites']; }
    $reached = count(array_unique(array_merge(...array_values($usedLabels[$suite] ?? [[]])) ?: [[]]));
    $out[] = '| ' . $suite . ' | `' . $meta['path'] . '` | ' . count($labels[$suite])
        . ' | ' . $sites . ' | ' . $reached . ' |';
}
$out[] = '';

file_put_contents(REPO . '/docs/traceability-matrix.md', implode("\n", $out) . "\n");

fwrite(STDERR, "traceability map OK\n");
fwrite(STDERR, "  requirements: $totals\n");
fwrite(STDERR, "  automated: $auto  partial: $part  manual: " . count($byStatus['manual']) . "  gap: " . count($byStatus['gap']) . "\n");
foreach (SUITES as $suite => $meta) {
    fwrite(STDERR, '  ' . $suite . ': ' . count($usedLabels[$suite] ?? []) . '/' . count($labels[$suite]) . " labels mapped\n");
}