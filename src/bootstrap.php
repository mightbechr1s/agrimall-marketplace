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
        // Ensure log directory exists
        $logDir = dirname($errorConfig['error_log']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        ini_set('error_log', $errorConfig['error_log']);
    }
}

// Set error reporting level based on environment
$isProduction = ($config['app']['env'] ?? 'development') === 'production';

if ($isProduction) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ---- Exception handler (production -> 500.php; dev -> default)  ----
set_exception_handler(function (Throwable $e) use ($config, $isProduction): void {
    // Always log full error + stack (daily-rotated log file)
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
        "[%s] %s in %s:%d\nStack trace:\n%s\n---\n",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($msg, 3, $dailyLog);

    // Prune rotated logs older than 14 days (once per request is fine; cheap).
    $cutoff = time() - 14 * 86400;
    foreach (glob($logDir . DIRECTORY_SEPARATOR . 'errors-*.log') ?: [] as $f) {
        if (filemtime($f) < $cutoff) {
            @unlink($f);
        }
    }

    if ($isProduction) {
        // Render clean 500 page
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $ref = substr(md5((string) microtime(true) . (string) random_bytes(4)), 0, 8);
        // Try to include 500.php with ref; if it fails, output minimal HTML
        $fiveHundred = __DIR__ . '/../public/500.php';
        if (is_file($fiveHundred)) {
            // Use output buffering to capture any output from 500.php
            ob_start();
            require $fiveHundred;
            exit;
        }
        // Fallback minimal response
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>500 Server Error</title></head><body style="font-family:system-ui;padding:2rem;text-align:center"><h1>Server Error</h1><p>Reference: <code>' . $ref . '</code></p></body></html>';
        exit;
    }
    // Development: let PHP's default handler display the error (already configured via display_errors=1)
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
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

    session_name('ANIMAL_SESSID');
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'httponly' => true,
        'secure'   => $isProduction, // true only over HTTPS (production)
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Security headers (default best-practice; loosened only behind HTTPS)  ----
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
    // Don't cache HTML pages: rendered output varies by login state, so a
    // heuristically cached copy would show stale navigation after login/logout.
    header('Cache-Control: no-store, private, must-revalidate');
    header('Pragma: no-cache');

    // CORS same-origin deny
    header('Access-Control-Allow-Origin: ');
}

// ---- Load helpers and core services  ----
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/DB.php';

try {
    DB::init($config['db']);
} catch (Throwable $e) {
    error_log('DB init failed: ' . $e->getMessage());
    // DB unavailable — pages using DB::pdo() will get RuntimeException
    // Pages that don't need DB will still work
}

// Resume a remembered session before any page reads the current user.
try {
    remember_resume();
} catch (Throwable $e) {
    error_log('Remember-me resume failed: ' . $e->getMessage());
}

return $config;