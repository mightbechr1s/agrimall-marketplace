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

/** Upper sanity bound for any submitted quantity, in PHP. */
const MAX_QUANTITY = 1000000;

/**
 * Bounded numeric reader for untrusted input.
 *
 * Returns null unless $raw is a plain decimal inside [$min, $max]; a bare
 * (float) cast would pass '12abc' as 12 and '1e999' as INF.
 */
function number_in($raw, float $min, float $max): ?float
{
    if (is_int($raw) || is_float($raw)) {
        $n = (float) $raw;
        return is_finite($n) && $n >= $min && $n <= $max ? $n : null;
    }
    if (!is_string($raw)) {
        return null;
    }
    $s = trim($raw);
    if ($s === '' || !preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $s)) {
        return null;
    }
    $n = (float) $s;
    return is_finite($n) && $n >= $min && $n <= $max ? $n : null;
}

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

/** Quantity for display: DECIMAL(10,4) arrives as "2.0000", shown as "2". */
function qty_str($value): string
{
    return rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
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

// =====================================================================
// Account security events and account erasure
// =====================================================================

/**
 * Append a security-relevant event to logs/security.log.
 *
 * admin_audit_log is deliberately not used here: audit_log() is a no-op
 * unless the current actor is an administrator, and admin_audit_log.admin_id
 * is ON DELETE RESTRICT, so it is the wrong home for events raised by
 * ordinary users acting on their own account (password reset, name change,
 * account erasure).
 *
 * Never pass raw personal values in $context. Record which fields changed and
 * the stable user id; the authoritative values stay in the users/profile
 * tables where retention and anonymization controls can reach them
 * (NFR-PRIV-010).
 */
function security_event_log(string $event, array $context = []): void
{
    global $config;
    try {
        $logDir = $config['app']['security_log'] ?? (__DIR__ . '/../logs/security.log');
        $logDir = dirname((string) $logDir);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $line = json_encode([
            'ts'    => date('c'),
            'event' => $event,
            'uid'   => current_user_id(),
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
            'ctx'   => $context,
        ], JSON_UNESCAPED_SLASHES);
        error_log((string) $line . PHP_EOL, 3, (string) ($config['app']['security_log'] ?? (__DIR__ . '/../logs/security.log')));
    } catch (Throwable $e) {
        // Never let logging break the request.
    }
}

/**
 * Whether password reset links may be surfaced in the page body.
 *
 * This is a local-only delivery channel with no mail transport behind it, so
 * it is refused outright in production: on a real deployment the same page
 * would otherwise print a live reset token to whoever submitted the form.
 */
function local_reset_links_enabled(): bool
{
    global $config;
    if (($config['app']['env'] ?? 'development') === 'production') {
        return false;
    }
    return !empty($config['app']['local_password_reset_links']);
}

/**
 * Deliver a password reset link over the local-only channel.
 *
 * There is no mailer in this project, so the raw token has to reach the
 * operator somehow. It is appended to logs/security.log for out-of-band
 * retrieval and, when local_reset_links_enabled() allows it, returned so the
 * caller can show it. Returns null when the link must not be displayed.
 */
function password_reset_deliver_local(int $userId, string $rawToken): ?string
{
    global $config;
    $base = rtrim((string) ($config['app']['base_url'] ?? ''), '/');
    $link = $base . '/reset.php?token=' . rawurlencode($rawToken);

    security_event_log('password_reset_link_issued', [
        'user_id'    => $userId,
        'delivered'  => 'local_log',
    ]);
    error_log(
        sprintf('[%s] password reset link for user #%d: %s', date('c'), $userId, $link) . PHP_EOL,
        3,
        (string) ($config['app']['security_log'] ?? (__DIR__ . '/../logs/security.log'))
    );

    return local_reset_links_enabled() ? $link : null;
}

/**
 * Drop outstanding password reset tokens for a user.
 *
 * Issuing a new token must invalidate the previous ones, otherwise every
 * earlier "reset my password" mail stays usable until it expires on its own.
 */
function password_resets_revoke(int $userId): void
{
    DB::pdo()->prepare('DELETE FROM password_resets WHERE user_id = :id')
        ->execute(['id' => $userId]);
}

/**
 * True when an administrator has placed an unreleased retention hold on the
 * user (FR-ADM-019, BR-034). An active hold outranks an erasure request.
 */
function user_has_active_retention_hold(int $userId): bool
{
    $stmt = DB::pdo()->prepare(
        "SELECT COUNT(*) FROM retention_holds
          WHERE entity_type = 'user' AND entity_id = :id AND released_at IS NULL"
    );
    $stmt->execute(['id' => $userId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Order states that still represent an in-flight commitment. */
const NONTERMINAL_ORDER_STATUSES = [
    'pending',
    'delivery_fee_review',
    'confirmed',
    'preparing',
    'ready_for_pickup',
    'out_for_delivery',
];

/**
 * Count a user's orders that have not reached a terminal state, on either the
 * buying or the selling side.
 *
 * FR-ADM-019 conditions anonymization on having no nonterminal orders. An
 * order still in flight names a counterparty who still needs to reach this
 * person, and cancelling or completing it on their behalf would be a
 * substantive decision made without consent.
 */
function user_nonterminal_order_count(int $userId): int
{
    $in  = implode(',', array_fill(0, count(NONTERMINAL_ORDER_STATUSES), '?'));
    $stmt = DB::pdo()->prepare(
        "SELECT COUNT(*) FROM orders
          WHERE (buyer_id = ? OR farmer_id = ?)
            AND status IN ($in)"
    );
    $stmt->execute(array_merge([$userId, $userId], NONTERMINAL_ORDER_STATUSES));
    return (int) $stmt->fetchColumn();
}

/**
 * Irreversibly anonymize an account (NFR-PRIV-006, NFR-PRIV-009).
 *
 * The users row and its primary key survive as the stable internal subject
 * reference that orders, listings and audit records point at; identifying
 * values are overwritten rather than the row being dropped. Profile rows are
 * scrubbed in place and never removed, because orders.buyer_id and
 * orders.farmer_id are ON DELETE RESTRICT through them and a physical delete
 * would fail for any account that has ever traded.
 *
 * Listings belonging to the user are archived rather than deleted
 * (NFR-REL-004); order quantities, prices, statuses and dates are preserved
 * while the free-text and exact-location fields that can carry personal data
 * are cleared.
 *
 * Throws RuntimeException if the transaction cannot be committed, so the
 * caller can refuse rather than report a deletion that did not happen.
 */
function account_anonymize(int $userId): array
{
    $pdo = DB::pdo();
    $summary = [];

    $pdo->beginTransaction();
    try {
        // 1. Neutralise the account itself. A random hash replaces the real
        //    one so the row can never authenticate again, and the tombstone
        //    address is unique per id so the email constraint still holds.
        $pdo->prepare(
            "UPDATE users
                SET email = :email,
                    password_hash = :hash,
                    status = 'anonymized',
                    anonymized_at = NOW(),
                    remember_token = NULL,
                    remember_expires = NULL
              WHERE id = :id AND status <> 'anonymized'"
        )->execute([
            'email' => 'deleted-user-' . $userId . '@anonymized.invalid',
            'hash'  => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'id'    => $userId,
        ]);

        // 2. Farmer identity fields. The row stays so listings and orders keep
        //    resolving to a real farmer_profiles key. barangay, municipality_city,
        //    province, region and farm_name, contact_person, address and
        //    valid_id_number are all NOT NULL, so those become placeholders
        //    rather than NULL; only genuinely nullable columns are cleared.
        $pdo->prepare(
            "UPDATE farmer_profiles
                SET farm_name = :farm_name,
                    farm_description = NULL,
                    contact_person = :contact_person,
                    contact_phone = NULL,
                    contact_email = NULL,
                    address = :address,
                    barangay = :barangay,
                    municipality_city = :municipality_city,
                    province = :province,
                    region = :region,
                    latitude = NULL,
                    longitude = NULL,
                    valid_id_number = :valid_id_number,
                    valid_id_image = NULL,
                    rejection_reason = NULL
              WHERE user_id = :id"
        )->execute([
            'farm_name'         => 'Anonymized Farm ' . $userId,
            'contact_person'    => 'Anonymized',
            'address'           => 'Anonymized',
            'barangay'          => 'Anonymized',
            'municipality_city' => 'Anonymized',
            'province'          => 'Anonymized',
            'region'            => 'Anonymized',
            'valid_id_number'   => 'ANON-' . $userId,
            'id'                => $userId,
        ]);

        // 3. Buyer identity fields.
        $pdo->prepare(
            "UPDATE buyer_profiles
                SET full_name = :full_name,
                    contact_phone = NULL,
                    delivery_address = NULL,
                    barangay = NULL,
                    municipality_city = NULL,
                    province = NULL,
                    region = NULL,
                    latitude = NULL,
                    longitude = NULL
              WHERE user_id = :id"
        )->execute([
            'full_name' => 'Anonymized User ' . $userId,
            'id'        => $userId,
        ]);

        // 4. Exact fulfillment locations and user-authored free text on
        //    historical orders (NFR-PRIV-009). Statuses, quantities, prices
        //    and dates are the non-identifying record that must survive.
        $orders = $pdo->prepare(
            'UPDATE orders
                SET delivery_address = NULL,
                    buyer_note = NULL,
                    farmer_note = NULL,
                    admin_note = NULL
              WHERE buyer_id = :buyer_id OR farmer_id = :farmer_id'
        );
        $orders->execute(['buyer_id' => $userId, 'farmer_id' => $userId]);
        $summary['orders_scrubbed'] = $orders->rowCount();

        // 5. Archive the user's listings instead of deleting them.
        $listings = $pdo->prepare(
            "UPDATE produce_listings
                SET status = 'archived'
              WHERE farmer_id = ? AND status <> 'archived'"
        );
        $listings->execute([$userId]);
        $summary['listings_archived'] = $listings->rowCount();

        // 6. Session and notification state cannot outlive the account. These
        //    only cascade on a physical user delete, which is not what happens
        //    here, so they are cleared explicitly.
        $tables = [
            'cart_items'         => 'buyer_id',
            'saved_listings'     => 'user_id',
            'notifications'      => 'user_id',
            'user_preferences'   => 'user_id',
            'user_suspensions'   => 'user_id',
            'password_resets'    => 'user_id',
        ];
        foreach ($tables as $table => $column) {
            $pdo->prepare("DELETE FROM `$table` WHERE `$column` = :id")
                ->execute(['id' => $userId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        security_event_log('account_anonymize_failed', ['target_user_id' => $userId]);
        throw new RuntimeException('Anonymization failed: ' . $e->getMessage(), 0, $e);
    }

    security_event_log('account_anonymized', [
        'target_user_id'    => $userId,
        'orders_scrubbed'   => $summary['orders_scrubbed'] ?? 0,
        'listings_archived' => $summary['listings_archived'] ?? 0,
    ]);

    return $summary;
}

function build_report_pdf(string $title, string $ref, array $filters, array $rows, ?array $totals): string
{
    $lines = [$title . ' (' . $ref . ')', 'Generated: ' . date('Y-m-d H:i:s T'), 'Filters: ' . implode('; ', $filters), ''];
    if ($rows) {
        foreach ($rows as $row) {
            $parts = [];
            foreach ($row as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $value = preg_replace('/[\r\n\t]+/', ' ', (string) $value);
                $value = str_replace('₱', 'PHP ', $value);
                $parts[] = $key . '=' . $value;
            }
            foreach (str_split(implode(' | ', $parts), 110) as $line) {
                $lines[] = $line;
            }
        }
    } else {
        $lines[] = '(No rows matched.)';
    }
    if ($totals !== null) {
        $lines[] = '';
        $lines[] = 'Totals: ' . implode(' | ', array_map(
            static fn ($key, $value): string => $key . '=' . (is_numeric($value) ? number_format((float) $value, 2) : $value),
            array_keys($totals),
            array_values($totals)
        ));
    }

    $pages = array_chunk($lines, 45);
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [PLACEHOLDER] /Count ' . count($pages) . ' >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pageRefs = [];
    foreach ($pages as $pageLines) {
        $content = "BT\n/F1 9 Tf\n40 800 Td\n";
        foreach ($pageLines as $line) {
            $content .= '(' . addcslashes($line, '\\()') . ") Tj\n0 -16 Td\n";
        }
        $content .= "ET\n";
        $contentObject = count($objects) + 1;
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        $pageObject = count($objects) + 1;
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
        $pageRefs[] = $pageObject . ' 0 R';
    }
    $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pages) . ' >>';

    $pdf = "%PDF-1.4\n%\\xE2\\xE3\\xCF\\xD3\n";
    $offsets = [0];
    foreach ($objects as $number => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($number + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf('%010d 00000 n %s', $offsets[$i], "\n");
    }

    return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
}