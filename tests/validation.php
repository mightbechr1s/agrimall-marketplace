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

// values that must be accepted
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

// coercion the old (float) cast silently accepted
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

// range enforcement
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

// constants the endpoints depend on
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

// listing_is_expired(): FR-LIST-033. Missing expiry means no shelf life, not expired.
$warned = [];
// Declared so traceability generator scrapes labels as 'valid::...' like other assertions.
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
// Expiry date is last sellable day; today stays buyable. Pinned explicitly so boundary cannot drift.
$check('a listing expiring today is still sellable', ['expiry_date' => '2026-10-05'], false, '2026-10-05');
$check('a listing expired yesterday is not sellable', ['expiry_date' => '2026-10-04'], true, '2026-10-05');

// Positive control: reader must return true sometimes; always-false stub would pass above.
if (listing_is_expired(['expiry_date' => '2000-01-01']) === true) {
    $pass++;
} else {
    $fail[] = 'listing_is_expired never reports an expired listing -- the reader cannot be trusted';
}

// Detail page passes PDO::fetch() stdClass, not array; array-only signature would fatal live.
if (listing_is_expired((object) ['expiry_date' => '2000-01-01']) === true
    && listing_is_expired((object) ['expiry_date' => '2099-01-01']) === false) {
    $pass++;
} else {
    $fail[] = 'listing_is_expired must accept a stdClass row as PDO::fetch() returns one';
}

// Missing expiry_date key must not warn on either shape: new listing without shelf life is normal.
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

// password_policy_error() is shared by register, account and reset.
$policyCases = [
    ['mixed password is accepted',       'Password1', null],
    ['minimum length with case + digit', 'Passw0rd',  null],
    ['short password fails on length',   'Pass1',     'err_pass_min'],
    ['empty password fails on length',   '',          'err_pass_min'],
    ['lowercase-only needs uppercase',   'weakpass',  'err_pass_upper'],
    ['missing digit is rejected',        'Password',  'err_pass_number'],
];
foreach ($policyCases as [$why, $pw, $want]) {
    $got = password_policy_error($pw);
    if ($got === $want) {
        $pass++;
    } else {
        $fail[] = sprintf(
            'password_policy_error: %s -- got %s, expected %s',
            $why,
            var_export($got, true),
            var_export($want, true)
        );
    }
}

// Returned dynamically, so i18n-parity.php does not cover these keys.
$copyDeck = require dirname(__DIR__) . '/src/i18n_copy.php';
$policyKeys = ['err_pass_min', 'err_pass_upper', 'err_pass_number'];
$slots = 0;
foreach (['auth', 'account'] as $block) {
    foreach (['en', 'tl'] as $loc) {
        foreach ($policyKeys as $k) {
            if (!empty($copyDeck[$block][$loc][$k])) {
                $slots++;
            }
        }
    }
}
if ($slots === count($policyKeys) * 4) {
    $pass++;
} else {
    $fail[] = sprintf('password-policy copy keys found in %d of %d deck slots', $slots, count($policyKeys) * 4);
}

// At-rest encryption for TOTP seeds and ID numbers.
$GLOBALS['config']['security']['encryption_key'] = base64_encode(str_repeat('K', 32));
if (sensitive_can_encrypt()) {
    $pass++;
} else {
    $fail[] = 'sensitive_can_encrypt() said no despite a 32-byte key';
}
$sealed = sensitive_encrypt('ABC-123456');
if (is_string($sealed) && str_starts_with($sealed, 'enc:v1:')) {
    $pass++;
} else {
    $fail[] = 'sensitive_encrypt() did not tag its output as ciphertext';
}
if ($sealed !== null && sensitive_decrypt($sealed) === 'ABC-123456') {
    $pass++;
} else {
    $fail[] = 'sensitive_encrypt/decrypt round-trip failed';
}
// A fixed IV would make identical inputs identical ciphertexts (and leak equality).
if ($sealed === sensitive_encrypt('ABC-123456')) {
    $fail[] = 'sensitive_encrypt() is deterministic -- IV is not random';
} else {
    $pass++;
}
if (sensitive_decrypt('legacy-plain-id') === 'legacy-plain-id') {
    $pass++;
} else {
    $fail[] = 'legacy plaintext did not pass through sensitive_decrypt()';
}
// Tampering with the blob must fail closed, not return garbage or plaintext.
$tampered = substr($sealed, 0, -3) . (substr($sealed, -3, 1) === 'A' ? 'B' : 'A') . substr($sealed, -2);
if (sensitive_decrypt($tampered) === '') {
    $pass++;
} else {
    $fail[] = 'tampered ciphertext did not fail closed';
}
// A wrong key must not decrypt (GCM tag check), and must not fatal.
$GLOBALS['config']['security']['encryption_key'] = base64_encode(str_repeat('Z', 32));
if (sensitive_decrypt($sealed) === '') {
    $pass++;
} else {
    $fail[] = 'a wrong key decrypted ciphertext';
}
// No key: encryption refuses, and the storage helper keeps the value usable.
$GLOBALS['config']['security']['encryption_key'] = '';
if (sensitive_encrypt('x') === null) {
    $pass++;
} else {
    $fail[] = 'sensitive_encrypt() claimed success without a key';
}
$prevLog = ini_set('error_log', sys_get_temp_dir() . '/agrimall-test-error.log');
if (sensitive_store('plain-value') === 'plain-value') {
    $pass++;
} else {
    $fail[] = 'sensitive_store() keyless fallback altered the value';
}
ini_set('error_log', (string) $prevLog);
// Restore a valid key for the rest of the file.
$GLOBALS['config']['security']['encryption_key'] = base64_encode(str_repeat('K', 32));

// TOTP (RFC 6238) against the SHA-1 appendix-B vectors. The ASCII secret
// "12345678901234567890" is encoded to base32 first, exactly as an account would
// store it. 6-digit codes are the low 6 digits of the published 8-digit values.
$totpSecret = totp_base32_encode('12345678901234567890');
if (totp_base32_decode($totpSecret) === '12345678901234567890') {
    $pass++;
} else {
    $fail[] = 'totp base32 round-trip changed the secret';
}
// Positive control: a decoder that always returned '' would pass nothing above,
// but re-check a known encoding so a broken encoder cannot hide behind the round-trip.
if ($totpSecret === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ') {
    $pass++;
} else {
    $fail[] = 'totp base32 encoding drifted from the RFC 4648 value: ' . $totpSecret;
}

$totpVectors = [
    [59,          '287082'],
    [1111111109,  '081804'],
    [1111111111,  '050471'],
    [1234567890,  '005924'],
    [2000000000,  '279037'],
    [20000000000, '353130'],
];
foreach ($totpVectors as [$epoch, $expected]) {
    $slice = (int) floor($epoch / 30);
    $got = totp_code($totpSecret, $slice);
    if ($got === $expected) {
        $pass++;
    } else {
        $fail[] = "totp_code at T=$epoch: got $got, expected $expected";
    }
    if (!totp_verify($totpSecret, $expected, $epoch)) {
        $fail[] = "totp_verify rejected the RFC code at T=$epoch";
    } else {
        $pass++;
    }
}

// One step of clock drift each way is accepted; two is not.
if (totp_verify($totpSecret, totp_code($totpSecret, (int) floor(1111111109 / 30)), 1111111109 + 29)
    && totp_verify($totpSecret, totp_code($totpSecret, (int) floor(1111111109 / 30)), 1111111109 - 29)) {
    $pass++;
} else {
    $fail[] = 'totp_verify must tolerate +/- one 30s step of clock drift';
}
if (totp_verify($totpSecret, totp_code($totpSecret, (int) floor(1111111109 / 30)), 1111111109 + 90)) {
    $fail[] = 'totp_verify accepted a code two steps out of window';
} else {
    $pass++;
}
// Shape checks: wrong digit count and no digits at all must not verify.
foreach (['', '12345', '1234567', 'abcdef'] as $bad) {
    if (totp_verify($totpSecret, $bad, 59)) {
        $fail[] = "totp_verify accepted malformed code '" . $bad . "'";
    } else {
        $pass++;
    }
}
// Pasting a grouped code from a password manager ("287 082") is a normal habit,
// so separators are stripped rather than treated as a failed attempt.
if (totp_verify($totpSecret, '287 082', 59) && totp_verify($totpSecret, '287-082', 59)) {
    $pass++;
} else {
    $fail[] = 'totp_verify rejected a grouped but otherwise correct code';
}

// Generated secrets must be 20 bytes of entropy in base32 (32 chars, RFC alphabet).
$gen = totp_generate_secret();
if (strlen($gen) === 32 && preg_match('/^[A-Z2-7]+$/', $gen) === 1 && $gen !== totp_generate_secret()) {
    $pass++;
} else {
    $fail[] = 'totp_generate_secret did not produce a distinct 32-char base32 secret';
}

// Provisioning URI must carry the secret and issuer for authenticator apps.
$uri = totp_provisioning_uri('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'buyer@animal.local');
if (str_starts_with($uri, 'otpauth://totp/')
    && str_contains($uri, 'secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ')
    && str_contains($uri, 'issuer=AgriMALL')) {
    $pass++;
} else {
    $fail[] = 'totp_provisioning_uri is missing the secret or issuer: ' . $uri;
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
