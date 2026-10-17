<?php
// Extract every requirement from docs/SRS.txt into JSON for inspection.
declare(strict_types=1);
$md = file_get_contents(__DIR__ . '/../docs/SRS.txt');
if ($md === false) { fwrite(STDERR, "cannot read SRS\n"); exit(1); }

preg_match_all('/\*\*((?:FR|NFR)-[A-Z]+-\d+)\s*:\*\*\s*(.+?)(?=\r?\n|$)/u', $md, $m, PREG_SET_ORDER);
$reqs = [];
foreach ($m as $hit) {
    $id  = $hit[1];
    $txt = trim(preg_replace('/\s+/u', ' ', $hit[2]));
    $txt = rtrim($txt, '.');
    [$kind, $mod] = explode('-', $id, 3) + [null, null, null];
    $reqs[] = ['id' => $id, 'kind' => $kind, 'module' => $mod, 'text' => $txt];
}
$byKind = [];
foreach ($reqs as $r) { $byKind[$r['kind']] = ($byKind[$r['kind']] ?? 0) + 1; }
$byMod  = [];
foreach ($reqs as $r) { $byMod[$r['module']] = ($byMod[$r['module']] ?? 0) + 1; }
ksort($byMod);
fwrite(STDERR, 'total: ' . count($reqs) . "\n");
fwrite(STDERR, 'by kind: ' . json_encode($byKind) . "\n");
fwrite(STDERR, 'by module: ' . json_encode($byMod) . "\n");
file_put_contents(
    __DIR__ . '/srs_requirements.json',
    json_encode($reqs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);
foreach ($reqs as $r) { echo $r['id'] . "\t" . $r['text'] . "\n"; }