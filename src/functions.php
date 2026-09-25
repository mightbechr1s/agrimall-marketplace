<?php
// Shared helpers: escaping, CSRF, flash messages, redirects.
declare(strict_types=1);

/** HTML-escape untrusted output (NFR-SEC-003). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Generate and store a CSRF token, or reuse the current one. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Emit a hidden CSRF field for forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '" />';
}

/** Validate a submitted CSRF token (NFR-SEC-002). Returns bool. */
function csrf_verify(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Fail the current request with a 403 on CSRF mismatch. */
function csrf_abort_if_invalid(?string $token): void
{
    if (!csrf_verify($token)) {
        http_response_code(403);
        exit('CSRF token mismatch.');
    }
}

/** Set a one-shot flash message. */
function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

/** Read and clear a flash message. */
function flash_get(string $key): ?string
{
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

/** Redirect to a path and stop execution. */
function redirect_to(string $path): void
{
    if (!headers_sent()) {
        header('Location: ' . $path, true, 302);
    }
    // JS fallback if headers already sent
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($path) . '"><script>window.location=' . json_encode($path) . '</script></head><body></body></html>';
    exit;
}

/** Current authenticated user id, or null. */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/** Current authenticated user's role, or null. */
function current_user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** Require a login; otherwise bounce to login page. */
function require_login(): void
{
    if (!current_user_id()) {
        redirect_to('/login.php');
    }
}

/** Require a specific role; otherwise bounce with 403. */
function require_role(string $role): void
{
    require_login();
    if (current_user_role() !== $role) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

/**
 * Cache-busting URL for a file under public/.
 * Uses the file mtime so versions never drift out of sync with the asset.
 */
function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $file = dirname(__DIR__) . '/public/' . $relative;
    return $relative . '?v=' . (is_file($file) ? filemtime($file) : '1');
}

/** Money formatting in Philippine pesos (BR-016). */
function money($amount): string
{
    return '₱' . number_format((float) $amount, 2, '.', ',');
}

/**
 * DB-backed rate limiter (replaces session-based counter that resets on new session).
 * Returns true if request should be blocked (too many attempts).
 */
function rate_limit_check(string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
{
    $pdo = DB::pdo();
    static $initialized = false;
    if (!$initialized) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            `key` VARCHAR(128) NOT NULL PRIMARY KEY,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            window_start INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $initialized = true;
    }

    $now = time();
    $stmt = $pdo->prepare("SELECT attempts, window_start FROM rate_limits WHERE `key` = :key");
    $stmt->execute(['key' => $key]);
    $row = $stmt->fetch();

    if (!$row || ($now - $row['window_start']) > $windowSeconds) {
        $ins = $pdo->prepare("INSERT INTO rate_limits (`key`, attempts, window_start) VALUES (:key, 1, :now) ON DUPLICATE KEY UPDATE attempts = 1, window_start = :now2");
        $ins->execute(['key' => $key, 'now' => $now, 'now2' => $now]);
        return false;
    }

    if ($row['attempts'] >= $maxAttempts) {
        return true;
    }

    $upd = $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE `key` = :key");
    $upd->execute(['key' => $key]);
    return false;
}

/** Short preview of a block of text for card teasers. */
function preview(?string $text, int $length = 110): string
{
    if ($text === null || $text === '') {
        return '';
    }
    return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
}

/** Generate a human order reference like AM-20260915-4F9E2C. */
function order_ref(): string
{
    return 'AM-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/** Append a row to order_status_history (same DB connection expected). */
function order_log_status(PDO $pdo, int $orderId, ?string $from, string $to, int $changedBy, string $role, ?string $reason = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, changed_by_role, reason)
         VALUES (:order_id, :from_status, :to_status, :changed_by, :changed_by_role, :reason)'
    );
    $stmt->execute([
        'order_id'        => $orderId,
        'from_status'     => $from,
        'to_status'       => $to,
        'changed_by'      => $changedBy,
        'changed_by_role' => $role,
        'reason'          => $reason,
    ]);
}

/** Record an administrative action in admin_audit_log. */
function audit_log(string $action, string $entityType, ?int $entityId, ?array $old = null, ?array $new = null): void
{
    $adminId = current_user_id();
    if ($adminId === null || current_user_role() !== 'admin') {
        return;
    }
    $stmt = DB::pdo()->prepare(
        'INSERT INTO admin_audit_log
            (admin_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
         VALUES (:admin_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip, :ua)'
    );
    $stmt->execute([
        'admin_id'    => $adminId,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'old_values'  => $old === null ? null : json_encode($old),
        'new_values'  => $new === null ? null : json_encode($new),
        'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'          => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
    ]);
}