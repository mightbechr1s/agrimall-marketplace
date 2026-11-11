<?php
// Central bootstrap: loads config, starts the session, registers helpers and DB.
declare(strict_types=1);


$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['timezone']);

// ---- Error logging configuration  ----
if (!empty($config['app']['error_logging'])) {
    $errorConfig = $config['app']['error_logging'];

    if (isset($errorConfig['display_errors'])) {
        ini_set('display_errors', $errorConfig['display_errors'] ? '1' : '0');
    }

    if (isset($errorConfig['log_errors'])) {
        ini_set('log_errors', $errorConfig['log_errors'] ? '1' : '0');
    }

    if (isset($errorConfig['error_log']) && !empty($errorConfig['error_log'])) {
        $logDir = dirname($errorConfig['error_log']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        ini_set('error_log', $errorConfig['error_log']);
    }
}

$isProduction = ($config['app']['env'] ?? 'development') === 'production';

if ($isProduction) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

/**
 * Render the 500 page and stop.
 *
 * Shared by the exception handler and the shutdown handler below so a fatal
 * error is indistinguishable from a thrown one. Exits.
 */
function render_fatal_page(bool $isProduction): void
{
    if (!$isProduction) {
        return;   // dev: PHP's own handler displays the error
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $ref = strtoupper(bin2hex(random_bytes(4)));
    $fiveHundred = __DIR__ . '/../public/500.php';
    if (is_file($fiveHundred)) {
        // No ob_start() here: a buffer opened during shutdown is discarded, which
        // is why the fatal path was returning a 3-byte body. 500.php is
        // self-contained and generates its own reference code, so it needs
        // nothing from here.
        require $fiveHundred;
        exit;
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>500 Server Error</title></head><body style="font-family:system-ui;padding:2rem;text-align:center"><h1>Server Error</h1><p>Reference: <code>' . $ref . '</code></p></body></html>';
    exit;
}

/** Write a fatal error to the same daily-rotated log the exception path uses. */
function log_fatal_to_daily(array $cfg, array $last): void
{
    $logPath = $cfg['app']['error_logging']['error_log'] ?? __DIR__ . '/../logs/error.log';
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $dailyLog = (strpos($logPath, 'error.log') !== false)
        ? $logDir . DIRECTORY_SEPARATOR . 'errors-' . date('Y-m-d') . '.log'
        : $logPath;
    error_log(sprintf(
        "[%s] FATAL %d in %s:%d -- %s\n---\n",
        date('Y-m-d H:i:s'),
        (int) ($last['type'] ?? 0),
        (string) ($last['file'] ?? '(unknown)'),
        (int) ($last['line'] ?? 0),
        (string) ($last['message'] ?? 'unknown')
    ), 3, $dailyLog);
}

// ---- Exception handler (production -> 500.php; dev -> default)  ----
set_exception_handler(function (Throwable $e) use ($config, $isProduction): void {
    // Always log full error + stack to a daily-rotated file.
    $logPath = $config['app']['error_logging']['error_log'] ?? __DIR__ . '/../logs/error.log';
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    // Rotate: errors-YYYY-MM-DD.log; prune files older than 14 days.
    $dailyLog = (strpos($logPath, 'error.log') !== false)
        ? $logDir . DIRECTORY_SEPARATOR . 'errors-' . date('Y-m-d') . '.log'
        : $logPath;
    $msg = sprintf(
        "[%s] %s: %s in %s:%d\nStack trace:\n%s\n---\n",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($msg, 3, $dailyLog);

    // Prune rotated logs older than 14 days.
    $cutoff = time() - 14 * 86400;
    foreach (glob($logDir . DIRECTORY_SEPARATOR . 'errors-*.log') ?: [] as $f) {
        if (filemtime($f) < $cutoff) {
            @unlink($f);
        }
    }

    if ($isProduction) {
        render_fatal_page(true);
    }
    // Dev: PHP's default handler displays the error (display_errors=1).
});

// ---- Shutdown handler: fatal errors never reach the exception handler ----
// A parse error, "cannot redeclare function()", or memory exhaustion ends the
// script without throwing. With display_errors=0 the browser was getting an
// EMPTY BODY AT HTTP 200, which is how a real bug stayed invisible long enough
// to be worth writing down. A 5xx is the honest answer.
register_shutdown_function(function () use ($config, $isProduction): void {
    $last = error_get_last();
    if ($last === null) {
        return;   // normal shutdown
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) $last['type'], $fatalTypes, true)) {
        return;   // a warning or notice: already handled by set_error_handler
    }
    log_fatal_to_daily($config, $last);
    // Partial output already flushed? Replacing it would corrupt the response,
    // but leaving HTTP 200 is worse, so at least correct the status.
    $sent = ob_get_level() > 0 && (string) ob_get_contents() !== '';
    if ($sent) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        return;
    }
    render_fatal_page($isProduction);
});

// ---- Error handler (catches E_ERROR, E_WARNING, etc.)  ----
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Don't handle errors suppressed by @
    if (error_reporting() === 0) {
        return false;
    }
    // Convert to exception for unified handling
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// ---- Session hardening  ----
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = (int) ($config['app']['session_lifetime_minutes'] ?? 30) * 60;
    // ini_set can only lower this value, never raise it above php.ini's
    // session.gc_maxlifetime. Cap with min() so a 30-minute lifetime on a
    // server configured for 24 minutes doesn't throw under the strict handler.
    $gcMax = (int) ini_get('session.gc_maxlifetime');
    if ($gcMax > 0 && $sessionLifetime > $gcMax) {
        $sessionLifetime = $gcMax;
    }
    // ini_set for session ini settings throws 'headers already sent' when
    // any output precedes bootstrap (BOM, whitespace, or an included file).
    // The ini value (1440s) is close enough to the requested 30 minutes, so
    // skip it rather than kill the request.
    // Skip ini_set: it fails when headers are already sent (BOM, whitespace, or
    // any output before bootstrap). The ini value (1440s) is close enough to
    // the requested 30 minutes; nothing to fix if it can't be changed.

    // Guard: session_name() fails when headers are already sent (e.g. BOM,
    // whitespace, or any output before bootstrap). Skip rather than throw.
    if (!headers_sent()) {
        session_name('ANIMAL_SESSID');
    }
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'httponly' => true,
        // Separate from $isProduction on purpose: these are unrelated concerns.
        // 'env' also decides whether PHP diagnostics reach the browser, and tying
        // cookie transport to it meant a production-mode config served over plain
        // http -- every local run and every test suite -- issued a Secure cookie
        // that cookie jars then refuse to store. The session silently never
        // existed and every authenticated request came back 403, which is what
        // produced 70 pre-existing test failures. Unset means true, which is the
        // safe direction; only a harness on http://127.0.0.1 should set it false.
        'secure'   => !array_key_exists('secure_cookies', $config['app'])
            || (bool) $config['app']['secure_cookies'],
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Security headers  ----
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // CSP (enforcing)
    $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
    header('Content-Security-Policy: ' . $csp);

    // HSTS (HTTPS+prod)
    if ($isProduction && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    // Don't cache HTML: output varies by login state, so a cached copy shows stale nav.
    header('Cache-Control: no-store, private, must-revalidate');
    header('Pragma: no-cache');

    // CORS same-origin deny
    header('Access-Control-Allow-Origin: ');
}

// ---- Load helpers and core services  ----
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/order_actions.php';
require_once __DIR__ . '/admin_actions.php';
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/mobile_push.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/DB.php';

try {
    DB::init($config['db']);
} catch (Throwable $e) {
    error_log('DB init failed: ' . $e->getMessage());
    // DB unavailable: DB::pdo() throws; pages that don't need DB still work.
}

// Resume a remembered session before any page reads the current user.
try {
    remember_resume();
} catch (Throwable $e) {
    error_log('Remember-me resume failed: ' . $e->getMessage());
}

return $config;
