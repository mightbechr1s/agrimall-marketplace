<?php
/** Guards number_in(): a cast-plus-compare passes '12abc' as 12 and INF as > 0.
 *  Migration 008 constrains these columns too; this covers the PHP half.
 *  Run: php tests/validation.php
 */
declare(strict_types=1);

$functionsPath = dirname(__DIR__) . '/src/functions.php';
if (!is_file($functionsPath)) {
    fwrite(STDERR, "FATAL: helpers not found at $functionsPath\n");
    exit(2);
}
require_once $functionsPath;

if (!function_exists('number_in')) {
    fwrite(STDERR, "FATAL: number_in() is not defined -- src/functions.php did not declare it\n");
    exit(2);
}

$pass = 0;
$fail = [];

/** Assert that number_in($raw, $min, $max) equals $expected. */
$check = function (string $label, $raw, float $min, float $max, ?float $expected) use (&$pass, &$fail): void {
    $actual = number_in($raw, $min, $max);
    if ($actual === $expected) {
        $pass++;
        return;
    }
    $shown = is_string($raw) ? "'" . $raw . "'" : var_export($raw, true);
    $fail[] = sprintf(
        '%-46s number_in(%s, %s, %s) = %s, expected %s',
        $label,
        $shown,
        $min,
        $max,
        $actual === null ? 'null' : var_export($actual, true),
        $expected === null ? 'null' : var_export($expected, true)
    );
};

// ---- values that must be accepted -------------------------------------
$check('integer string',            '10',     0.0, 100.0, 10.0);
$check('decimal string',            '10.5',   0.0, 100.0, 10.5);
$check('leading decimal point',     '.5',     0.0, 100.0, 0.5);
$check('trailing decimal point',    '5.',     0.0, 100.0, 5.0);
$check('explicit plus sign',        '+7',     0.0, 100.0, 7.0);
$check('surrounding whitespace',    '  42  ', 0.0, 100.0, 42.0);
$check('native int',                3,        0.0, 100.0, 3.0);
$check('native float',              2.5,      0.0, 100.0, 2.5);
$check('lower bound is inclusive',  '0',      0.0, 100.0, 0.0);
$check('upper bound is inclusive',  '100',    0.0, 100.0, 100.0);
$check('negative within range',     '-5',   -10.0, 10.0, -5.0);

// ---- coercion that the old (float) cast silently accepted --------------
$check('letters only',              'abc',    0.0, 100.0, null);
$check('trailing junk',             '12abc',  0.0, 100.0, null);
$check('leading junk',              'abc12',  0.0, 100.0, null);
$check('empty string',              '',       0.0, 100.0, null);
$check('whitespace only',           '   ',    0.0, 100.0, null);
$check('thousands separator',       '1,000',  0.0, 5000.0, null);
$check('scientific overflow',       '1e999',  0.0, 100.0, null);
$check('scientific notation',       '1e5',    0.0, 100.0, null);
$check('hex literal',               '0x1A',   0.0, 100.0, null);
$check('INF spelled out',           'INF',    0.0, 100.0, null);
$check('NaN spelled out',           'NaN',    0.0, 100.0, null);
$check('null',                      null,     0.0, 100.0, null);
$check('boolean',                   true,     0.0, 100.0, null);
$check('array',                     ['1'],    0.0, 100.0, null);
$check('object',                    new stdClass(), 0.0, 100.0, null);

// ---- range enforcement ------------------------------------------------
$check('below lower bound',         '-0.5',   0.0, 100.0, null);
$check('above upper bound',         '100.5',  0.0, 100.0, null);
$check('zero rejected when min > 0', '0',     0.01, 100.0, null);
$check('zero accepted when min = 0', '0',      0.0, 100.0, 0.0);
$check('negative rejected for money', '-1',    0.0, MAX_DELIVERY_FEE, null);
$check('fee at cap',                (string) MAX_DELIVERY_FEE, 0.0, MAX_DELIVERY_FEE, (float) MAX_DELIVERY_FEE);
$check('fee above cap',             (string) (MAX_DELIVERY_FEE + 1), 0.0, MAX_DELIVERY_FEE, null);

// available_quantity case: null must not pass as a valid non-negative figure
$garbage = number_in('not-a-number', 0.0, MAX_QUANTITY);
$check('garbage stock is rejected', 'not-a-number', 0.0, (float) MAX_QUANTITY, null);
if ($garbage === null) {
    $pass++;
} else {
    $fail[] = 'regression: unparseable stock input produced a usable number';
}

// ---- constants the endpoints depend on --------------------------------
if (MAX_QUANTITY > 0 && MAX_DELIVERY_FEE > 0) {
    $pass++;
} else {
    $fail[] = 'MAX_QUANTITY and MAX_DELIVERY_FEE must both be positive';
}

// a reader that rejected everything would pass every case above; re-check known-good values
$knownGood = [['10', 0.0, 100.0, 10.0], ['10.5', 0.0, 100.0, 10.5],
              ['.5', 0.0, 100.0, 0.5], ['  42  ', 0.0, 100.0, 42.0],
              ['0', 0.0, 100.0, 0.0], ['100', 0.0, 100.0, 100.0]];
$acceptsThatWorked = 0;
foreach ($knownGood as $c) {
    if (number_in($c[0], $c[1], $c[2]) === $c[3]) {
        $acceptsThatWorked++;
    }
}
if ($acceptsThatWorked === count($knownGood)) {
    $pass++;
} else {
    $fail[] = sprintf(
        'reader accepts only %d of %d known-good values -- a reject-all stub cannot be ruled out',
        $acceptsThatWorked,
        count($knownGood)
    );
}

// listing_is_expired(): FR-LIST-033. A missing expiry means no shelf life was
// declared, which is not the same as expired.
$warned = [];
// Declared so the traceability generator scrapes these labels as 'valid::...'
// like every other assertion in this file.
$check = function (string $label, $listing, bool $wantExpired, ?string $today = null) use (&$pass, &$fail): void {
    if (listing_is_expired($listing, $today) === $wantExpired) {
        $pass++;
        return;
    }
    $fail[] = 'listing_is_expired: ' . $label;
};
$expiryCases = [
    ['no expiry set is not expired',            ['expiry_date' => null],                          false],
    ['blank expiry is not expired',              ['expiry_date' => ''],                            false],
    ['unparseable expiry is not expired',        ['expiry_date' => 'not-a-date'],                  false],
    ['a future expiry is not expired',           ['expiry_date' => '2099-01-01'],                  false],
    ['today is still sellable',                  ['expiry_date' => date('Y-m-d')],                 false],
    ['yesterday has expired',                    ['expiry_date' => date('Y-m-d', strtotime('-1 day'))],  true],
    ['last year has expired',                    ['expiry_date' => date('Y-m-d', strtotime('-1 year'))], true],
];
foreach ($expiryCases as [$why, $listing, $wantExpired]) {
    $check($why, $listing, $wantExpired);
}
// The expiry date is the last sellable day, so today itself must stay buyable.
// Pinned explicitly rather than via date() so the boundary cannot drift.
$check('a listing expiring today is still sellable', ['expiry_date' => '2026-10-05'], false, '2026-10-05');
$check('a listing expired yesterday is not sellable', ['expiry_date' => '2026-10-04'], true, '2026-10-05');

// Positive control: the reader must be capable of returning true at all, so a
// stub that always says "not expired" cannot satisfy the cases above.
if (listing_is_expired(['expiry_date' => '2000-01-01']) === true) {
    $pass++;
} else {
    $fail[] = 'listing_is_expired never reports an expired listing -- the reader cannot be trusted';
}

// The detail page passes a PDO::fetch() stdClass, not an array, so a signature
// that only accepts arrays would fatal on the live page while passing above.
if (listing_is_expired((object) ['expiry_date' => '2000-01-01']) === true
    && listing_is_expired((object) ['expiry_date' => '2099-01-01']) === false) {
    $pass++;
} else {
    $fail[] = 'listing_is_expired must accept a stdClass row as PDO::fetch() returns one';
}

// A missing expiry_date key must not warn, on either shape: a new listing row
// that simply has no shelf life is normal, and a notice there would break the
// page for every listing without an expiry.
set_error_handler(static function ($no, $str) use (&$warned) {
    $warned[] = $str;
    return true;
});
listing_is_expired([]);
listing_is_expired((object) []);
$restored = restore_error_handler();
if (!$warned) {
    $pass++;
} else {
    $fail[] = 'listing_is_expired emitted a notice for a row with no expiry_date: ' . $warned[0];
}

echo "assertions passed: $pass\n";
echo "assertions failed: " . count($fail) . "\n";

if ($fail === []) {
    echo "OK: number_in() accepts plain decimals and rejects coerced garbage\n";
    exit(0);
}
foreach ($fail as $f) {
    echo "  $f\n";
}
fwrite(STDERR, 'VALIDATION FAILURES: ' . count($fail) . "\n");
exit(1);
