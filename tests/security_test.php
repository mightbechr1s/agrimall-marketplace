<?php
/**
 * Guards two non-functional requirements that are easy to regress silently.
 *
 *   NFR-SEC-001  All SQL queries containing variable data shall use PDO
 *                prepared statements.
 *   NFR-SEC-011  Production error pages shall not expose stack traces, SQL
 *                statements, credentials, or server paths.
 *
 * Both were violated by the first implementation and fixed in review:
 *  - NFR-SEC-001: dashboard.php concatenated a date into SQL; account
 *    anonymization interpolated table/column identifiers with no allow-list.
 *  - NFR-SEC-011: four catch blocks flashed $e->getMessage() straight to the
 *    user, so a PDOException rendered its SQLSTATE and table name on screen.
 *
 * Run: php tests/security_test.php
 */
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$functionsPath = $repoRoot . '/src/functions.php';
if (!is_file($functionsPath)) {
    fwrite(STDERR, "FATAL: helpers not found at $functionsPath\n");
    exit(2);
}
require_once $functionsPath;

$pass = 0;
$fail = [];

$check = function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        return;
    }
    $fail[] = $label . ($detail !== '' ? "  <- $detail" : '');
};

// ---------------------------------------------------------------------------
echo "NFR-SEC-011: unexpected exception text never reaches the user\n";
// ---------------------------------------------------------------------------
$dbUser = 'root';
$dbPass = '';
$dsn = 'mysql:host=127.0.0.1;dbname=animal_marketplace;charset=utf8mb4';
$probeTable = 'table_that_must_not_exist';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // Provoke a genuine database error rather than a synthetic string.
    $pdo->query('SELECT * FROM ' . $probeTable);
    $check('probe query should have failed', false, 'it did not throw');
} catch (Throwable $e) {
    $raw = $e->getMessage();
    $ref = app_log_exception($e, 'security_test');
    $shown = generic_error_message($ref);

    // The whole point: the real message is damning, the shown one is not.
    $check('raw PDO message really does leak SQLSTATE', stripos($raw, 'SQLSTATE') !== false, substr($raw, 0, 80));
    $check('raw PDO message really does name the table', stripos($raw, $probeTable) !== false, substr($raw, 0, 80));

    $check('user-facing message hides SQLSTATE', stripos($shown, 'SQLSTATE') === false, $shown);
    $check('user-facing message hides the table name', stripos($shown, $probeTable) === false, $shown);
    $check('user-facing message hides SQL keywords', stripos($shown, 'SELECT') === false, $shown);
    $check('user-facing message hides a file path', !preg_match('~[A-Za-z]:\\\\~', $shown), $shown);
    $check('user-facing message offers a reference code', (bool) preg_match('/Reference: [0-9a-f]{8}$/', $shown), $shown);
    $check('reference code is 8 hex chars', (bool) preg_match('/^[0-9a-f]{8}$/', $ref), $ref);

    // The detail must still reach the log, or support has nothing to work with.
    $logFile = $repoRoot . '/logs/errors-' . date('Y-m-d') . '.log';
    $check('daily error log exists', is_file($logFile), $logFile);
    if (is_file($logFile)) {
        $tail = (string) file_get_contents($logFile);
        $check('log retains the full SQL detail', stripos($tail, $probeTable) !== false);
        $check('log retains a stack trace', stripos($tail, 'Stack trace:') !== false);
        $check('log records the reference code', str_contains($tail, 'ref=' . $ref));
    }
}

// ---------------------------------------------------------------------------
echo "\nNFR-SEC-011: deliberate, user-safe errors still show their own text\n";
// ---------------------------------------------------------------------------
// A rejected action must tell the farmer *why*, so the friendly path must work.
try {
    throw new UserFacingException('Only an active listing can be paused.');
} catch (UserFacingException $e) {
    $check('friendly message passes through unchanged', $e->getMessage() === 'Only an active listing can be paused.', $e->getMessage());
}
$check('UserFacingException is catch-compatible as RuntimeException', is_subclass_of(UserFacingException::class, RuntimeException::class));

// Every deliberate throw must be marked, or its message would be swallowed by
// the default-deny catch and the farmer would get a useless generic error.
$unmarked = [];
foreach (glob($repoRoot . '/public/*.php') ?: [] as $file) {
    $src = (string) file_get_contents($file);
    if (preg_match_all('/throw new RuntimeException\(/', $src, $m)) {
        $unmarked[] = basename($file) . ' (' . count($m[0]) . ')';
    }
}
$check('no page throws an unmarked RuntimeException', $unmarked === [], implode(', ', $unmarked));

// The leak is getMessage() reaching a *user-facing* sink from a catch that was
// not explicitly sanctioned. Safety needs both:
//   1. the catch type is UserFacingException (message written for a person)
//   2. otherwise the message must never reach flash_set/echo/print/session
// error_log() is always fine: the daily log exists to capture detail without
// showing it, so it is not exposure.
$USER_SINKS = '/(?:flash_set\s*\(|echo\s|print\s|\$_(?:SESSION|GET|POST)\[)/';
$leaky = [];
$catchesSeen = 0;
foreach (glob($repoRoot . '/public/*.php') ?: [] as $file) {
    $src = (string) file_get_contents($file);
    // Walk each `} catch (Type $var) { ... }` body, allowing one nesting level.
    if (!preg_match_all('/catch\s*\(\s*([A-Za-z\\\\?]+)[^)]*\)\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s', $src, $m, PREG_SET_ORDER)) {
        continue;
    }
    foreach ($m as $match) {
        $catchesSeen++;
        $type = ltrim($match[1], '\\?');
        $body = $match[2];
        if ($type === 'UserFacingException') {
            continue;   // sanctioned: this message was written for a human
        }
        if (str_contains($body, '->getMessage()') && preg_match($USER_SINKS, $body)) {
            $leaky[] = basename($file) . ' (catch ' . $type . ')';
        }
    }
}
$check('no unsanctioned catch routes an exception message to the user', $leaky === [], implode(', ', $leaky));
$check('the scan actually inspected catch blocks', $catchesSeen > 0, "found $catchesSeen");

// ...and the detail must still be captured server-side somewhere.
$logged = 0;
foreach (glob($repoRoot . '/public/*.php') ?: [] as $file) {
    $logged += preg_match_all('/error_log\([^;]{0,120}?->getMessage\(\)/', (string) file_get_contents($file));
}
$check('unexpected errors are still logged server-side', $logged > 0, "found $logged");

// The sanctioned path must exist, or friendly errors would be swallowed.
$userFacingCatches = 0;
foreach (glob($repoRoot . '/public/*.php') ?: [] as $file) {
    $userFacingCatches += preg_match_all('/catch\s*\(\s*UserFacingException/', (string) file_get_contents($file));
}
$check('UserFacingException catches exist to show friendly text', $userFacingCatches > 0, "found $userFacingCatches");

// ---------------------------------------------------------------------------
echo "\nNFR-SEC-001: no request superglobal reaches a SQL statement\n";
// ---------------------------------------------------------------------------
$superglobalHits = [];
foreach (array_merge(glob($repoRoot . '/public/*.php') ?: [], glob($repoRoot . '/public/partials/*.php') ?: [], glob($repoRoot . '/src/*.php') ?: []) as $file) {
    $lines = file($file) ?: [];
    foreach ($lines as $i => $line) {
        if (!preg_match('/\$_(GET|POST|COOKIE|REQUEST)\b/', $line)) {
            continue;
        }
        // Danger shape: request data on a line that also builds or runs SQL.
        // Deliberately matches SQL verbs and real call syntax only -- a bare
        // "query" also matches variables like `$query = $_GET` in i18n.php,
        // which is URL building, not SQL.
        if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i', $line)
            || preg_match('/->\s*(prepare|query|exec)\s*\(/', $line)) {
            $superglobalHits[] = basename($file) . ':' . ($i + 1);
        }
    }
}
$check('no $_GET/$_POST/$_COOKIE appears on a SQL line', $superglobalHits === [], implode(', ', $superglobalHits));

// Identifier interpolation cannot be parameterised, so it must be allow-listed.
$anonymize = (string) file_get_contents($repoRoot . '/src/functions.php');
$check(
    'account anonymization allow-lists its interpolated identifiers',
    str_contains($anonymize, '$allowedColumns')
        && str_contains($anonymize, 'Refusing to clear unknown table'),
    'identifier interpolation in account_anonymize has no guard'
);

// ---------------------------------------------------------------------------
echo "\nassertions passed: $pass\n";
echo 'assertions failed: ' . count($fail) . "\n";

if ($fail === []) {
    echo "OK: error pages leak no SQL/paths, and request data never reaches SQL\n";
    exit(0);
}
foreach ($fail as $f) {
    echo "  $f\n";
}
fwrite(STDERR, 'SECURITY FAILURES: ' . count($fail) . "\n");
exit(1);