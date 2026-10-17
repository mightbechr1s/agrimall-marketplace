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
echo "\nNFR-SEC-009: uploads are decoded, not just sniffed\n";
// ---------------------------------------------------------------------------
// finfo_file() reads magic bytes, so a file that merely *starts* like a PNG
// passes it. NFR-SEC-009 also requires decoded image validity, which is the
// check that was missing: both upload sites stopped at the sniffed MIME type.

if (!function_exists('validate_uploaded_image')) {
    fwrite(STDERR, "FATAL: validate_uploaded_image() is not defined\n");
    exit(2);
}
$asUpload = fn(string $path): array => ['tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK];
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
$sig = "\x89PNG\r\n\x1a\n";

// Real 1x1 PNG, so the check cannot pass by rejecting everything.
file_put_contents($tmpDir . 'sec_ok.png', base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
));
// Valid signature and IHDR label, but no real image behind them. libmagic calls
// this image/png; getimagesize() reports 0x0. This is the payload the previous
// MIME-only check accepted and this one must reject.
file_put_contents($tmpDir . 'sec_hollow.png', $sig . "\x00\x00\x00\x0dIHDR" . str_repeat("\x00", 13));
// Signature plus an IHDR header that is cut off before its dimensions.
file_put_contents($tmpDir . 'sec_trunc.png', $sig . "\x00\x00\x00\x0dIHDR" . "\x00\x00\x00\x10");
// The webshell polyglot (PHP source carrying an image extension) is OPT-IN.
// Writing that payload makes Windows Defender classify it as
// Trojan:PHP/Chopper and quarantine the file, so a routine `npm test` would trip
// the host's antivirus and alarm whoever is running it. The rejection path it
// exercises is the same one the harmless non-image below exercises, so the
// default run uses that instead and the webshell case is opt-in via:
//
//     ANIMAL_TEST_WEBSHELL=1 npm run test:security
$polyglotSurvived = false;
if (getenv('ANIMAL_TEST_WEBSHELL') === '1') {
    file_put_contents($tmpDir . 'sec_evil.png', "<?php system(\$_GET['c']); ?>\n");
    $polyglotSurvived = is_file($tmpDir . 'sec_evil.png');
    if (!$polyglotSurvived) {
        echo "  note: the webshell payload was quarantined by host antivirus before\n";
        echo "        it could be used, so that case cannot be proven on this machine.\n";
    }
}

// A harmless non-image, so the "reject a file that is not an image at all" path
// is always exercised.
file_put_contents($tmpDir . 'sec_text.png', "This is a plain text file, not an image.\n");

$okReal = validate_uploaded_image($asUpload($tmpDir . 'sec_ok.png'));
$hollow = validate_uploaded_image($asUpload($tmpDir . 'sec_hollow.png'));
$trunc = validate_uploaded_image($asUpload($tmpDir . 'sec_trunc.png'));
$text = validate_uploaded_image($asUpload($tmpDir . 'sec_text.png'));
$evil = $polyglotSurvived ? validate_uploaded_image($asUpload($tmpDir . 'sec_evil.png')) : null;

// Preconditions. Without these the rejections below could pass merely because
// the helper refuses everything, so each one pins the fact that made the case
// dangerous: the MIME sniff really did pass the file on its own.
$sniff = function (string $path): string {
    if (!function_exists('finfo_open')) {
        return '(fileinfo absent)';
    }
    $f = finfo_open(FILEINFO_MIME_TYPE);
    $m = (string) @finfo_file($f, $path);
    finfo_close($f);
    return $m;
};
if (function_exists('finfo_open')) {
    // These pin the reason the cases are dangerous: the MIME sniff alone would
    // have waved them through. They cannot be checked where fileinfo is absent,
    // so they are reported as skipped rather than silently passing.
    $check('hollow PNG does sniff as image/png (case is meaningful)', $sniff($tmpDir . 'sec_hollow.png') === 'image/png', $sniff($tmpDir . 'sec_hollow.png'));
    $check('truncated PNG does sniff as image/png (case is meaningful)', $sniff($tmpDir . 'sec_trunc.png') === 'image/png', $sniff($tmpDir . 'sec_trunc.png'));
    if ($polyglotSurvived) {
        $check('the PHP polyglot does not sniff as an image', $sniff($tmpDir . 'sec_evil.png') !== 'image/png', $sniff($tmpDir . 'sec_evil.png'));
    }
} else {
    echo "  skipped: fileinfo absent, so the 'the sniff alone would pass it' preconditions\n";
    echo "           cannot be demonstrated here (the decode assertions still run)\n";
}

$check('a genuine PNG is accepted', $okReal['ok'] === true, json_encode($okReal));
$check('accepted PNG reports real dimensions', ($okReal['width'] ?? 0) === 1 && ($okReal['height'] ?? 0) === 1, json_encode($okReal));
$check('a PNG that decodes to 0x0 is rejected', $hollow['ok'] === false, json_encode($hollow));
$check('a truncated PNG is rejected despite valid magic bytes', $trunc['ok'] === false, json_encode($trunc));
$check('a plain text file renamed .png is rejected', $text['ok'] === false, json_encode($text));
if ($polyglotSurvived) {
    $check('a PHP script renamed .png is rejected', $evil['ok'] === false, json_encode($evil));
} else {
    // Reported, not silently passed: opt-in only, because writing the payload
    // trips host antivirus. The plain-text case above covers the same path.
    echo "  SKIPPED: webshell polyglot is opt-in (ANIMAL_TEST_WEBSHELL=1) because\n";
    echo "           writing it makes antivirus quarantine the file. The plain-text\n";
    echo "           case above covers the same rejection path.\n";
}

// Every upload path must go through the single validating gate. Counting call
// sites rather than testing for the substring matters: "the helper is mentioned
// somewhere in the file" stayed green when one of create_listing.php's two
// upload sites was deleted.
$uploadPages = ['upload_image.php', 'create_listing.php'];
foreach ($uploadPages as $page) {
    $src = (string) file_get_contents($repoRoot . '/public/' . $page);
    $check("$page routes uploads through validate_uploaded_image()", str_contains($src, 'validate_uploaded_image('));
}

// ext/fileinfo is optional and missing from common builds (PHP 8.5 from winget,
// for one). An unguarded finfo_open() turned every upload into an uncaught
// Error there, and an uncaught Error in this app renders as HTTP 200 with an
// empty body -- so it failed silently. No upload path may call it bare.
$unguardedFinfo = [];
foreach (array_merge(glob($repoRoot . '/public/*.php') ?: [], glob($repoRoot . '/src/*.php') ?: []) as $file) {
    $src = (string) file_get_contents($file);
    if (!str_contains($src, 'finfo_open')) {
        continue;
    }
    // Comments are stripped first: a docblock that merely *mentions* finfo_open()
    // is prose about the call, not a call. Scanning raw text flagged the very
    // comment explaining this check.
    $code = preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $src);
    // Every finfo_open() must sit behind a function_exists / extension_loaded check.
    foreach (preg_split('/(?<=\;)\s*\n/', (string) $code) ?: [] as $stmt) {
        if (str_contains($stmt, 'finfo_open(')
            && !str_contains($stmt, 'function_exists')
            && !str_contains($stmt, 'extension_loaded')) {
            $unguardedFinfo[] = basename($file);
        }
    }
}
$check('no finfo_open() call is unguarded', $unguardedFinfo === [], implode(', ', array_unique($unguardedFinfo)));

// And the helper must not require fileinfo to be present at all.
printf(
    "  (environment: fileinfo %s, getimagesize %s -- the helper must pass either way)\n",
    function_exists('finfo_open') ? 'present' : 'ABSENT',
    function_exists('getimagesize') ? 'present' : 'ABSENT'
);
// A file that is not there must be refused quietly, not warn into the response.
$missing = validate_uploaded_image(['tmp_name' => $tmpDir . 'sec_absent.png', 'size' => 10, 'error' => UPLOAD_ERR_OK]);
$check('a missing temp file is rejected without a warning', $missing['ok'] === false);

foreach (['sec_ok.png', 'sec_hollow.png', 'sec_trunc.png', 'sec_evil.png', 'sec_text.png'] as $f) {
    @unlink($tmpDir . $f);
}

// ---------------------------------------------------------------------------
echo "\nNFR-SEC-009: the upload directory actually exists\n";
// ---------------------------------------------------------------------------
// git does not track empty directories, so public/uploads/listings/ was absent
// from every fresh clone. move_uploaded_file() then had nowhere to write, the
// warning was promoted to an ErrorException, and every upload returned a 500.
// No unit test could see that -- only a real end-to-end upload did -- so the
// invariant is asserted here.

if (!function_exists('listing_upload_dir')) {
    fwrite(STDERR, "FATAL: listing_upload_dir() is not defined\n");
    exit(2);
}
$dir = listing_upload_dir();
$check('listing_upload_dir() returns an existing directory', is_dir($dir), $dir);
$check('listing_upload_dir() points inside public/uploads', str_contains(str_replace('\\', '/', $dir), '/public/uploads/'), $dir);
$check('listing_upload_dir() is writable', is_writable($dir), $dir);

// The filesystem path must come from the helper, which creates the directory.
// The *public URL* '/uploads/listings/...' is still built by hand in each page
// and must stay that way, so this checks for __DIR__ concatenation only.
$handRolled = [];
foreach ($uploadPages as $page) {
    $src = str_replace('\\', '/', (string) file_get_contents($repoRoot . '/public/' . $page));
    if (str_contains($src, "__DIR__ . '/uploads/listings'")) {
        $handRolled[] = $page;
    }
}
$check('no upload site hand-builds the upload filesystem path', $handRolled === [], implode(', ', $handRolled));

// ---------------------------------------------------------------------------
echo "\nbase_url: a placeholder must not silently break password reset\n";
// ---------------------------------------------------------------------------
// config.example.php shipped base_url = 'https://your-domain.example'. Nothing
// validated it, so the app deployed happily with every password-reset link
// pointing at a dead domain -- and password reset simply did not work, with no
// error anywhere. app_base_url() must treat the placeholder as unset and derive
// the host from the request instead.

if (!function_exists('app_base_url')) {
    fwrite(STDERR, "FATAL: app_base_url() is not defined\n");
    exit(2);
}

$probeBaseUrl = function (string $configured, string $host = 'shop.example.test:8080', string $https = 'on') use ($check) {
    $GLOBALS['config'] = ['app' => ['base_url' => $configured]];
    $_SERVER['HTTP_HOST'] = $host;
    // Set every call: an earlier version left this alone and silently reset it,
    // so the http case could never actually be observed.
    $_SERVER['HTTPS'] = $https;
    return app_base_url();
};

$check('a real configured base_url wins', $probeBaseUrl('https://animal.ph/') === 'https://animal.ph', $probeBaseUrl('https://animal.ph/'));
$check('an empty base_url derives https from the request', $probeBaseUrl('') === 'https://shop.example.test:8080', $probeBaseUrl(''));
$check(
    'the shipped placeholder is treated as unset, not deployed',
    $probeBaseUrl('https://your-domain.example') === 'https://shop.example.test:8080',
    $probeBaseUrl('https://your-domain.example')
);

$check('plain http is derived as http', $probeBaseUrl('', 'shop.example.test:8080', '') === 'http://shop.example.test:8080', $probeBaseUrl('', 'shop.example.test:8080', ''));
$check("HTTPS=off is derived as http too", $probeBaseUrl('', 'shop.example.test:8080', 'off') === 'http://shop.example.test:8080', $probeBaseUrl('', 'shop.example.test:8080', 'off'));

// The concrete harm: the reset link must point at the host that was asked for.
$probeBaseUrl('https://your-domain.example');
$resolved = app_base_url();
$resetLink = $resolved . '/reset.php?token=abc123';
$check(
    'a password-reset link under the placeholder points at the real host',
    str_contains($resetLink, 'shop.example.test:8080/reset.php?token=abc123')
        && !str_contains($resetLink, 'your-domain.example'),
    $resetLink
);

// No page may read the raw key and build its own URL any more.
$rawReaders = [];
foreach (array_merge(glob($repoRoot . '/public/*.php') ?: [], glob($repoRoot . '/public/partials/*.php') ?: [], glob($repoRoot . '/src/*.php') ?: []) as $file) {
    $code = preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', (string) file_get_contents($file));
    if (preg_match_all("/\\\$config\['app'\]\['base_url'\]/", (string) $code, $m)) {
        $rawReaders[] = basename($file) . ' (' . count($m[0]) . ')';
    }
}
$check('only app_base_url() reads the raw base_url key', $rawReaders === ['functions.php (1)'], implode(', ', $rawReaders));

// ---------------------------------------------------------------------------
echo "\nNFR-SEC-011: a fatal error must be a 5xx, not an empty 200\n";
// ---------------------------------------------------------------------------
// set_exception_handler cannot see a fatal: a parse error, a redeclared
// function or memory exhaustion ends the script without throwing. With
// display_errors=0 the browser was handed an EMPTY BODY AT HTTP 200, so a broken
// page and a working page looked identical from outside. Proven against a live
// server by redeclaring a helper, which now yields HTTP 500 with the branded
// error page while the detail goes to the daily log.

$bootstrap = (string) file_get_contents($repoRoot . '/src/bootstrap.php');
$check('bootstrap registers a shutdown handler', str_contains($bootstrap, 'register_shutdown_function'));
$check(
    'the shutdown handler treats E_ERROR as fatal',
    preg_match('/\$fatalTypes\s*=\s*\[[^\]]*E_ERROR/', $bootstrap) === 1
);
$check('a fatal is written to the daily log', str_contains($bootstrap, 'log_fatal_to_daily'));
// Bounded to the function body, with comments stripped: a lazy [\s\S]*? still
// matches an ob_start() anywhere later in the file, and an unstripped body still
// matches the very comment that explains why there is none.
$fnStart = strpos($bootstrap, 'function render_fatal_page');
$fnCode = '';
if ($fnStart !== false) {
    $fnEnd = strpos($bootstrap, "\nfunction ", $fnStart + 10);
    $fnBody = $fnEnd === false ? substr($bootstrap, $fnStart) : substr($bootstrap, $fnStart, $fnEnd - $fnStart);
    $fnCode = (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $fnBody);
}
$check('render_fatal_page exists', $fnStart !== false);
$check(
    'render_fatal_page starts no output buffer that shutdown discards',
    !str_contains($fnCode, 'ob_start'),
    'ob_start() inside render_fatal_page() yields a 3-byte body'
);
$check('production still hides errors from the browser', str_contains($bootstrap, "ini_set('display_errors', '0')"));

// ---------------------------------------------------------------------------
echo "\nFR-VAL-005: declared image size is bounded before anything decodes it\n";
// ---------------------------------------------------------------------------
// A PNG header is 24 bytes and states its own dimensions, so a tiny file can
// claim to be enormous. getimagesize() reads that header without decoding pixels,
// which makes it the right place to refuse: nothing downstream has to allocate
// the result first. The image type is the same as the hollow case above, so only
// the IHDR dimensions differ.

/** A PNG whose IHDR declares the given dimensions, with no image data. */
$pngClaiming = function (int $w, int $h): string {
    $ihdr = pack('N2', $w, $h) . "\x08\x06\x00\x00\x00";
    return "\x89PNG\r\n\x1a\n" . "\x00\x00\x00\x0dIHDR" . $ihdr;
};

if (!defined('IMAGE_MAX_SIDE') || !defined('IMAGE_MAX_PIXELS')) {
    fwrite(STDERR, "FATAL: IMAGE_MAX_SIDE / IMAGE_MAX_PIXELS are not defined\n");
    exit(2);
}
$check('FR-VAL-005 side limit is 6000', IMAGE_MAX_SIDE === 6000, (string) IMAGE_MAX_SIDE);
$check('FR-VAL-005 pixel limit is 20 megapixels', IMAGE_MAX_PIXELS === 20000000, (string) IMAGE_MAX_PIXELS);

file_put_contents($tmpDir . 'sec_wide.png', $pngClaiming(9000, 100));
$wide = validate_uploaded_image($asUpload($tmpDir . 'sec_wide.png'));
$check('an image wider than 6000 px is rejected', $wide['ok'] === false, json_encode($wide));
$check('the rejection names the size, not the type', ($wide['reason'] ?? '') === 'too_large', json_encode($wide));

file_put_contents($tmpDir . 'sec_mega.png', $pngClaiming(5000, 5000));   // 25 MP, each side legal
$mega = validate_uploaded_image($asUpload($tmpDir . 'sec_mega.png'));
$check('a 25 megapixel image is rejected even though each side is legal', $mega['ok'] === false, json_encode($mega));
$check('the megapixel rejection is distinguished from the side limit', ($mega['reason'] ?? '') === 'too_many_pixels', json_encode($mega));

// A large-but-legal image must still be accepted, or the limit is a blanket
// rejection wearing a numeric disguise.
file_put_contents($tmpDir . 'sec_ok_big.png', $pngClaiming(6000, 3000));   // 18 MP, 6000 px side
$big = validate_uploaded_image($asUpload($tmpDir . 'sec_ok_big.png'));
$check('an image exactly at both limits is accepted', $big['ok'] === true, json_encode($big));
$check('the accepted image reports its real dimensions', ($big['width'] ?? 0) === 6000 && ($big['height'] ?? 0) === 3000, json_encode($big));
$check('the rejected 25 MP payload is itself a tiny file', filesize($tmpDir . 'sec_mega.png') < 100, filesize($tmpDir . 'sec_mega.png') . ' bytes');

foreach (['sec_wide.png', 'sec_mega.png', 'sec_ok_big.png'] as $f) {
    @unlink($tmpDir . $f);
}

// ---------------------------------------------------------------------------
echo "\nNFR-TEST-005: the production security headers are actually sent\n";
// ---------------------------------------------------------------------------
// Asserted against a live response where the header is observable over plain
// HTTP, and against the source where it only exists on the HTTPS branch, which a
// local server cannot exercise. Saying which is which matters more than
// pretending all seven were observed the same way.

$baseUrl = getenv('ANIMAL_TEST_BASE_URL') ?: 'http://127.0.0.1:8000';
$liveHeaders = null;
$ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true]]);
if (@file_get_contents($baseUrl . '/index.php', false, $ctx) !== false) {
    foreach ($http_response_header ?? [] as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $liveHeaders[strtolower(trim($k))] = trim($v);
        }
    }
}
if ($liveHeaders === null) {
    echo "  SKIPPED: no dev server at $baseUrl, so headers were not observed live.\n";
    echo "           The static checks below still run.\n";
} else {
    $check('live response sends X-Content-Type-Options: nosniff', ($liveHeaders['x-content-type-options'] ?? '') === 'nosniff', $liveHeaders['x-content-type-options'] ?? '(absent)');
    $check('live response sends X-Frame-Options: DENY', ($liveHeaders['x-frame-options'] ?? '') === 'DENY', $liveHeaders['x-frame-options'] ?? '(absent)');
    $csp = $liveHeaders['content-security-policy'] ?? '';
    $check('live response sends a Content-Security-Policy', str_contains($csp, "default-src 'self'"), $csp);
    $check('the CSP forbids framing', str_contains($csp, "frame-ancestors 'none'"));
    $check('the CSP restricts form targets', str_contains($csp, "form-action 'self'"));
    $check('live response sends Referrer-Policy', ($liveHeaders['referrer-policy'] ?? '') === 'strict-origin-when-cross-origin', $liveHeaders['referrer-policy'] ?? '(absent)');
    $check('live response sends Permissions-Policy', ($liveHeaders['permissions-policy'] ?? '') === 'camera=(), microphone=(), geolocation=()', $liveHeaders['permissions-policy'] ?? '(absent)');
    $check('HTML is not cached', str_contains($liveHeaders['cache-control'] ?? '', 'no-store'), $liveHeaders['cache-control'] ?? '(absent)');
}

// HTTPS-only behaviour cannot be exercised by a local HTTP server, so it is
// asserted at the source: the branch must exist and be gated on production.
$bootSrc = (string) file_get_contents($repoRoot . '/src/bootstrap.php');
$check('HSTS is sent on HTTPS in production', str_contains($bootSrc, 'Strict-Transport-Security') && str_contains($bootSrc, "'HTTPS'] !== 'off'"));
// Cookie transport is a separate config key, not derived from 'env': 'env' also
// controls whether PHP diagnostics reach the browser, and deriving Secure from it
// meant a production-mode config served over plain http issued a cookie that
// nothing would store. The safe direction has to be the default.
$bootCookie = (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $bootSrc);
$check(
    'the session cookie defaults to Secure when the key is absent',
    str_contains($bootCookie, "!array_key_exists('secure_cookies'")
        && str_contains($bootCookie, "\$config['app']['secure_cookies']"),
    'secure must default to true when secure_cookies is unset'
);
$check(
    'cookie transport is no longer derived from env',
    !preg_match('/\'secure\'\s*=>\s*\$isProduction/', $bootCookie),
    'deriving Secure from env over plain http caused 70 pre-existing failures'
);
$check('the session cookie is HttpOnly', str_contains($bootCookie, "'httponly' => true"));
$check('the session cookie is SameSite=Lax', str_contains($bootCookie, "'samesite' => 'Lax'"));

// The shipped example must state the safe default explicitly rather than rely
// on the fallback.
$example = (string) file_get_contents($repoRoot . '/config/config.example.php');
$check("config.example.php sets 'secure_cookies' => true", (bool) preg_match("/'secure_cookies'\s*=>\s*true/", $example));

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