<?php
// Shared helpers: escaping, CSRF, flash messages, redirects.
declare(strict_types=1);

/**
 * Server-side character limits for user-submitted fields, kept in sync
 * with the VARCHAR/TEXT columns in database/schema.sql. Password caps at
 * 72 so bcrypt (PASSWORD_DEFAULT) never silently truncates.
 */
const FIELD_LIMITS = [
    'email' => 255,
    'full_name' => 100,
    'farm_name' => 150,
    'farm_description' => 2000,
    'contact_person' => 100,
    'contact_phone' => 20,
    'barangay' => 100,
    'municipality_city' => 100,
    'province' => 100,
    'region' => 100,
    'valid_id_number' => 50,
    'name' => 150,
    'description' => 5000,
    'delivery_address' => 1000,
    'buyer_note' => 500,
    'reason' => 300,
    'password' => 72,
    'q' => 100,
];

/** Upper bound for a farmer-set delivery fee, in PHP. */
const MAX_DELIVERY_FEE = 1000000;

/** Return [field => max] for submitted fields that exceed FIELD_LIMITS. */
function over_limit_fields(array $input): array
{
    $over = [];
    foreach (FIELD_LIMITS as $field => $max) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        if (mb_strlen((string) $input[$field]) > $max) {
            $over[$field] = $max;
        }
    }
    return $over;
}

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

// ---- Remember me  ----
// Backed by users.remember_token / remember_expires (migration 002). Only the
// SHA-256 hash of the token is persisted, so a database leak cannot be replayed
// as a login. Lookup is by that hash, which is an index hit and doubles as the
// constant-time-equivalent verification.

/** Cookie attributes shared by the issue and revoke paths. */
function remember_cookie_args(int $expires): array
{
    global $config;

    return [
        'expires'  => $expires,
        'path'     => '/',
        'httponly' => true,
        'secure'   => ($config['app']['env'] ?? 'development') === 'production',
        'samesite' => 'Lax',
    ];
}

/** Issue a long-lived remember-me cookie for $userId. */
function remember_login(int $userId, int $days = 30): void
{
    $raw = bin2hex(random_bytes(32));
    $expires = time() + $days * 86400;

    DB::pdo()->prepare(
        'UPDATE users SET remember_token = :t, remember_expires = :x WHERE id = :id'
    )->execute([
        't'  => hash('sha256', $raw),
        'x'  => date('Y-m-d H:i:s', $expires),
        'id' => $userId,
    ]);

    setcookie('animal_remember', $raw, remember_cookie_args($expires));
}

/**
 * Revoke remember-me: clear the cookie plus the stored token, both for an
 * explicit $userId and for whichever row the cookie's token points at.
 */
function remember_revoke(?int $userId = null): void
{
    $pdo = DB::pdo();

    if ($userId !== null) {
        $pdo->prepare(
            'UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    $raw = $_COOKIE['animal_remember'] ?? '';
    if (is_string($raw) && preg_match('/^[a-f0-9]{64}$/', $raw)) {
        $pdo->prepare(
            'UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE remember_token = :t'
        )->execute(['t' => hash('sha256', $raw)]);
    }

    setcookie('animal_remember', '', remember_cookie_args(time() - 42000));
}

/**
 * Resume a session from the remember-me cookie when no PHP session is active.
 *
 * ponytail: one remembered device per user (users.remember_token is a single
 * slot, so a second remember-login invalidates the first) and the token is not
 * rotated on use, which means no selector-based reuse detection. A dedicated
 * remember_tokens table with selector/validator pairs is the upgrade if
 * multi-device or theft detection ever becomes a requirement.
 */
function remember_resume(): void
{
    if (isset($_SESSION['user_id'])) {
        return;
    }

    $raw = $_COOKIE['animal_remember'] ?? '';
    if (!is_string($raw) || !preg_match('/^[a-f0-9]{64}$/', $raw)) {
        remember_revoke();
        return;
    }

    $stmt = DB::pdo()->prepare(
        'SELECT id, role, status FROM users
          WHERE remember_token = :t AND remember_expires > NOW() LIMIT 1'
    );
    $stmt->execute(['t' => hash('sha256', $raw)]);
    $row = $stmt->fetch();

    // Expired or non-active rows are revoked rather than left to re-query the
    // database on every future request.
    if (!$row || $row['status'] !== 'active') {
        remember_revoke();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['role'] = $row['role'];
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

/** True if $path is a safe in-site relative path we can redirect/return to. */
function is_safe_path(string $path): bool
{
    if ($path === '' || $path[0] === '/' || $path[0] === '\\' || strpos($path, '//') !== false) {
        return false;
    }
    return preg_match('~^[A-Za-z0-9_./?=&:#%+\-]+$~', $path) === 1;
}

/** Login page URL, optionally bouncing back to a safe in-site path after login. */
function login_url(string $return = ''): string
{
    $url = 'login.php';
    if (is_safe_path($return)) {
        $url .= '?return_to=' . rawurlencode($return);
    }
    return $url;
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

/**
 * Real produce photograph used when a listing has no image of its own.
 *
 * Every card surface must render a photograph. The old fallbacks were a flat
 * colour swatch (assets/images/listings/mangoes.svg, captioned "Carabao
 * Mangoes" regardless of the product) and a file that did not exist at all
 * (assets/images/placeholder-produce.svg), which produced grey boxes and broken
 * image icons. Listing images live in listing_images.image_path; this is only
 * the last resort.
 *
 * Returns a bare relative path, NOT an asset() URL: callers assign it to
 * image_path and then run it through asset() themselves. Returning a URL here
 * produced a doubled version query ("photo.jpg?v=1&v=1").
 */
function fallback_produce_image(): string
{
    return 'assets/images/products/market.jpg';
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

    // Notify the other parties, never the actor. Uses the same connection, so a
    // rolled-back status change drops its notifications too.
    try {
        $lookup = $pdo->prepare('SELECT id, order_number, buyer_id, farmer_id FROM orders WHERE id = :id');
        $lookup->execute(['id' => $orderId]);
        $order = $lookup->fetch();
        if ($order) {
            if ($role === 'buyer') {
                $recipients = [(int) $order['farmer_id']];
            } elseif ($role === 'farmer') {
                $recipients = [(int) $order['buyer_id']];
            } else {
                $recipients = [(int) $order['buyer_id'], (int) $order['farmer_id']];
            }
            foreach ($recipients as $recipient) {
                if ($recipient !== $changedBy) {
                    notify_order_status($pdo, $recipient, $order, $from, $to, $reason);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('order status notification failed: ' . $e->getMessage());
    }
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

/** Read one user's notification preferences, falling back to the schema defaults. */
function notify_prefs(int $userId): array
{
    $prefs = [
        'notify_inapp'      => 1,
        'notify_orders'     => 1,
        'notify_messages'   => 1,
        'notify_promotions' => 0,
    ];
    try {
        $stmt = DB::pdo()->prepare(
            'SELECT notify_inapp, notify_orders, notify_messages, notify_promotions
             FROM user_preferences WHERE user_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            foreach (array_keys($prefs) as $key) {
                if (array_key_exists($key, $row)) {
                    $prefs[$key] = (int) $row[$key];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('notify_prefs failed: ' . $e->getMessage());
    }
    return $prefs;
}

/**
 * Deliver a notification to one user, honouring their settings page choices.
 * Never throws: a notification must not roll back the action that caused it.
 */
function notify_user(PDO $pdo, int $userId, string $category, string $title, ?string $body = null, ?string $link = null): void
{
    if ($userId <= 0) {
        return;
    }
    $prefs = notify_prefs($userId);
    if (empty($prefs['notify_' . $category] ?? 0)) {
        return;
    }
    if (!empty($prefs['notify_inapp'])) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, category, title, body, link)
                 VALUES (:user_id, :category, :title, :body, :link)'
            );
            $stmt->execute([
                'user_id'  => $userId,
                'category' => $category,
                'title'    => mb_substr($title, 0, 160),
                'body'     => $body === null ? null : mb_substr($body, 0, 400),
                'link'     => $link === null ? null : mb_substr($link, 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('notify in-app insert failed: ' . $e->getMessage());
        }
    }
}

/** Notify one user that an order they are party to changed status. */
function notify_order_status(PDO $pdo, int $userId, array $order, ?string $from, string $to, ?string $reason): void
{
    $phrases = [
        'pending'             => 'was placed and is pending review',
        'delivery_fee_review' => 'is awaiting a delivery-fee quote',
        'confirmed'           => 'was confirmed',
        'preparing'           => 'is being prepared',
        'ready_for_pickup'    => 'is ready for pickup',
        'out_for_delivery'    => 'is out for delivery',
        'completed'           => 'was completed',
        'rejected'            => 'was rejected',
        'cancelled'           => 'was cancelled',
    ];
    $phrase = $phrases[$to] ?? ('was updated to ' . str_replace('_', ' ', (string) $to));
    $title = 'Order ' . $order['order_number'] . ' ' . $phrase;
    $body = $reason ? 'Note: ' . $reason : null;
    notify_user($pdo, $userId, 'orders', $title, $body, 'order.php?id=' . (int) $order['id']);
}

/** Count a user's unread in-app notifications. Returns 0 on any failure. */
function unread_notification_count(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    try {
        $stmt = DB::pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :id AND read_at IS NULL');
        $stmt->execute(['id' => $userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}