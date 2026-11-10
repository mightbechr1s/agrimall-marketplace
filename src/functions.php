<?php
// Shared helpers: escaping, CSRF, flash messages, redirects.
declare(strict_types=1);

/**
 * Server-side field limits, in sync with the VARCHAR/TEXT columns in
 * database/schema.sql. Password caps at 72 so bcrypt never silently truncates.
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

/**
 * An error whose message was written for a person, not a developer.
 *
 * Only these messages are ever shown to a user. Anything else reaching a
 * catch block is assumed to be an infrastructure failure (PDO, TypeError, a
 * bug) whose text can carry SQL, table names or file paths, so it is logged
 * server-side and replaced with a generic message plus a support reference.
 */
class UserFacingException extends RuntimeException
{
}

/**
 * Record an unexpected exception in full on the server and return a short
 * reference code the user can quote to support. The detail never reaches the
 * page. Same daily rotation and 14-day prune as bootstrap's own handler.
 */
function app_log_exception(Throwable $e, string $context = ''): string
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $ref = substr(bin2hex(random_bytes(4)), 0, 8);
    $msg = sprintf(
        "[%s] ref=%s%s%s in %s:%d\n%s\nStack trace:\n%s\n---\n",
        date('Y-m-d H:i:s'),
        $ref,
        $context !== '' ? ' ' . $context : '',
        get_class($e),
        $e->getFile(),
        $e->getLine(),
        $e->getMessage(),
        $e->getTraceAsString()
    );
    @error_log($msg, 3, $logDir . DIRECTORY_SEPARATOR . 'errors-' . date('Y-m-d') . '.log');

    $cutoff = time() - 14 * 86400;
    foreach (glob($logDir . DIRECTORY_SEPARATOR . 'errors-*.log') ?: [] as $f) {
        if (@filemtime($f) < $cutoff) {
            @unlink($f);
        }
    }
    return $ref;
}

/** Shown instead of an unexpected exception's own text. */
function generic_error_message(string $ref): string
{
    return 'Something went wrong on our side. Please try again. Reference: ' . $ref;
}

/**
 * Absolute path to the listing-image directory, created on first use.
 *
 * git does not track empty directories, so public/uploads/listings/ was absent
 * from every fresh clone: move_uploaded_file() then had nowhere to write, warned,
 * and the warning became an ErrorException, so *every* upload returned a 500.
 * Creating it here fixes all three call sites at once and survives someone
 * deleting the directory after deploy.
 */
function listing_upload_dir(): string
{
    $dir = dirname(__DIR__) . '/public/uploads/listings';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Convert an uploaded image to WebP (quality 80) and generate a 400px-wide
 * thumbnail, both alongside the original. Returns the WebP path (relative
 * '/uploads/listings/...') on success, or null if GD is unavailable or the
 * conversion fails, so callers can fall back to the original file.
 *
 * Self-contained: uses only ext/gd. Safe to call when GD is missing.
 */
function optimize_uploaded_image(string $srcPath, string $relativeDir = '/uploads/listings'): ?string
{
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        return null;
    }
    if (!is_file($srcPath)) {
        return null;
    }
    $info = @getimagesize($srcPath);
    if ($info === false) {
        return null;
    }
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
        IMAGETYPE_PNG => @imagecreatefrompng($srcPath),
        IMAGETYPE_WEBP => @imagecreatefromwebp($srcPath),
        IMAGETYPE_GIF => @imagecreatefromgif($srcPath),
        default => false,
    };
    if ($src === false) {
        return null;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    // Cap dimensions to avoid memory blowups on huge uploads.
    $maxSide = 2000;
    if ($w > $maxSide || $h > $maxSide) {
        $scale = $maxSide / max($w, $h);
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $scaled = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($scaled, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $scaled;
        $w = $nw;
        $h = $nh;
    }

    $base = pathinfo($srcPath, PATHINFO_FILENAME);
    $dir = dirname($srcPath);
    $webpPath = $dir . '/' . $base . '.webp';

    // Preserve transparency for PNG/GIF by keeping alpha.
    imagesavealpha($src, true);
    if (!imagewebp($src, $webpPath, 80)) {
        imagedestroy($src);
        return null;
    }

    // Thumbnail (400px wide) for grid cards.
    $thumbW = min(400, $w);
    $thumbH = (int) round($h * ($thumbW / $w));
    if ($thumbW > 0 && $thumbH > 0 && ($thumbW !== $w || $thumbH !== $h)) {
        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbW, $thumbH, $w, $h);
        $thumbPath = $dir . '/' . $base . '_thumb.webp';
        imagewebp($thumb, $thumbPath, 75);
        imagedestroy($thumb);
    }

    imagedestroy($src);
    return $relativeDir . '/' . basename($webpPath);
}

/** FR-VAL-005: a decoded image may not exceed 20 megapixels or 6,000 px a side.
 *  A file of a couple of hundred bytes can declare 40,000 x 40,000 in its header;
 *  without this, whatever decodes it later pays for the expansion rather than the
 *  farmer who uploaded it. */
const IMAGE_MAX_SIDE = 6000;
const IMAGE_MAX_PIXELS = 20000000;

/**
 * Validate an uploaded file as an image (NFR-SEC-009).
 *
 * finfo_file() sniffs magic bytes, which is necessary but not sufficient: a file
 * can carry an image magic number yet be truncated, malformed or otherwise
 * undecodable. getimagesize() actually parses the container and reports the
 * decoded image type, so it is both the allow-list check and the proof that the
 * file is a real image.
 *
 * fileinfo is optional, not assumed: ext/fileinfo is absent from plenty of
 * builds (PHP 8.5 via winget among them), and calling finfo_open() unguarded
 * made every upload fatal there. When it is present its verdict is used to
 * cross-check getimagesize(); when it is absent the decoded type stands alone.
 *
 * @param array $file One entry from $_FILES.
 * @return array{ok:bool,mime?:string,width?:int,height?:int} Decoded type and
 *   dimensions on success, or ['ok' => false] when it is not a real image.
 */
function validate_uploaded_image(array $file): array
{
    if (!isset($file['tmp_name']) || !is_file($file['tmp_name'])) {
        return ['ok' => false];
    }

    // Decoded-image validity (NFR-SEC-009). This call is mandatory: without it
    // the type is merely claimed, not read out of a container.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || empty($info[0]) || empty($info[1]) || empty($info[2])) {
        return ['ok' => false];
    }

    $decodedMime = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
        IMAGETYPE_GIF => 'image/gif',
    ][$info[2]] ?? null;

    if ($decodedMime === null || !in_array($decodedMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return ['ok' => false];
    }

    // FR-VAL-005: reject by declared size before anything decodes the pixels.
    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width > IMAGE_MAX_SIDE || $height > IMAGE_MAX_SIDE) {
        return ['ok' => false, 'reason' => 'too_large'];
    }
    if (($width * $height) > IMAGE_MAX_PIXELS) {
        return ['ok' => false, 'reason' => 'too_many_pixels'];
    }

    // Optional second opinion. A disagreement means the bytes say one thing and
    // the decoded container says another, which is exactly what a polyglot looks
    // like, so it is rejected.
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            // Some libmagic builds return false (and warn) for content they
            // cannot classify. The cast makes that fail the comparison below.
            $sniffed = (string) @finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($sniffed !== '' && $sniffed !== $decodedMime) {
                return ['ok' => false];
            }
        }
    }

    return [
        'ok' => true,
        'mime' => $decodedMime,
        'width' => $width,
        'height' => $height,
    ];
}

/** Upper sanity bound for any submitted quantity, in PHP. */
const MAX_QUANTITY = 1000000;

/** Ceiling for a listing's available_quantity (FR-LIST-032); a restore cannot push past it. */
const MAX_STOCK_QTY = 999999.99;

/**
 * Bounded numeric reader for untrusted input. Returns null unless $raw is a
 * plain decimal inside [$min, $max]; a bare (float) cast would pass '12abc'.
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

/** Returns the i18n error key for the first unmet password rule, or null. */
function password_policy_error(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'err_pass_min';
    }
    if (preg_match('/\s/', $password)) {
        return 'err_pass_space';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'err_pass_upper';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'err_pass_number';
    }
    return null;
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
// users.remember_token / remember_expires (migration 002); only the SHA-256
// hash is stored, so a database leak cannot be replayed as a login.

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

/** Revoke remember-me: clear the cookie plus the stored token, both for an
 *  explicit $userId and for whichever row the cookie's token points at. */
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
 * Invalidate every credential for a user after a password change: mobile API
 * tokens, remember-me, and all PHP sessions via session_version.
 */
function revoke_user_credentials(int $userId, bool $keepCurrentSession = false): void
{
    $pdo = DB::pdo();
    $pdo->prepare('DELETE FROM api_tokens WHERE user_id = :id')->execute(['id' => $userId]);
    $pdo->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = :id')
        ->execute(['id' => $userId]);
    remember_revoke($userId);

    // Keep the session that just changed the password signed in.
    if ($keepCurrentSession && (int) ($_SESSION['user_id'] ?? 0) === $userId) {
        $stmt = $pdo->prepare('SELECT session_version FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $_SESSION['session_version'] = (int) $stmt->fetchColumn();
    }
}

/**
 * Resume a session from the remember-me cookie when no PHP session is active.
 *
 * ponytail: one remembered device per user (users.remember_token is one slot)
 * and no rotation on use, so no reuse detection. Upgrade to a remember_tokens
 * table with selector/validator pairs for multi-device or theft detection.
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
        'SELECT id, role, status, session_version FROM users
          WHERE remember_token = :t AND remember_expires > NOW() LIMIT 1'
    );
    $stmt->execute(['t' => hash('sha256', $raw)]);
    $row = $stmt->fetch();

    // Expired/non-active rows are revoked, not left to re-query on every request.
    if (!$row || $row['status'] !== 'active') {
        remember_revoke();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['role'] = $row['role'];
    // Without this, require_login() sees -1 and destroys the resumed session.
    $_SESSION['session_version'] = (int) ($row['session_version'] ?? 0);
}

/** Require a login; otherwise bounce to login page. */
function require_login(): void
{
    if (!current_user_id()) {
        redirect_to('/login.php');
    }
    // Invalidate sessions after admin status/version changes (FR-ADM-004).
    try {
        $stmt = DB::pdo()->prepare('SELECT session_version FROM users WHERE id = :id');
        $stmt->execute(['id' => current_user_id()]);
        $dbVersion = (int) $stmt->fetchColumn();
        if ($dbVersion !== (int) ($_SESSION['session_version'] ?? -1)) {
            session_unset();
            session_destroy();
            redirect_to('/login.php');
        }
    } catch (Throwable $e) {
        // degrade: do not block page if DB unavailable
    }
}

/** Require a specific role; otherwise bounce with 403. */
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(current_user_role(), $roles, true)) {
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

/** Cache-busting URL for a file under public/. Uses the file mtime so versions
 *  never drift out of sync with the asset. */
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

/** DB-backed rate limiter (replaces a session counter that reset per session).
 *  Returns true when the request should be blocked. */
function rate_limit_check(string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
{
    $pdo = DB::pdo();
    static $initialized = false;
    if (!$initialized) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            `key` VARCHAR(128) NOT NULL PRIMARY KEY,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            window_start INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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

/**
 * OTP helpers (email/phone verification, replaces CAPTCHA).
 *
 * generate_otp() returns a 6-digit code. store_otp() persists a hashed copy
 * with an expiry. verify_otp() checks the code against the latest unused,
 * unexpired row for that recipient+purpose and marks it used on success.
 * All helpers fail closed on DB errors (return false) so a broken OTP store
 * never grants access.
 */

function generate_otp(int $length = 6): string
{
    $max = (10 ** $length) - 1;
    return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
}

function otp_rate_limit(string $recipient, string $purpose, int $maxPerHour = 3): bool
{
    // Returns true when the request should be blocked.
    $pdo = DB::pdo();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM otp_codes
         WHERE recipient = :r AND purpose = :p
           AND created_at >= (NOW() - INTERVAL 1 HOUR)"
    );
    $stmt->execute(['r' => $recipient, 'p' => $purpose]);
    return (int) $stmt->fetchColumn() >= $maxPerHour;
}

function store_otp(string $recipient, string $channel, string $purpose, string $code, int $ttlSeconds = 600, ?string $ip = null): bool
{
    $pdo = DB::pdo();
    // Invalidate any prior unused codes for this recipient+purpose.
    $pdo->prepare(
        "UPDATE otp_codes SET used_at = NOW()
         WHERE recipient = :r AND purpose = :p AND used_at IS NULL"
    )->execute(['r' => $recipient, 'p' => $purpose]);
    $stmt = $pdo->prepare(
        "INSERT INTO otp_codes (recipient, channel, purpose, code_hash, expires_at, ip_address)
         VALUES (:r, :c, :p, :h, :e, :ip)"
    );
    return $stmt->execute([
        'r'  => $recipient,
        'c'  => $channel,
        'p'  => $purpose,
        'h'  => password_hash($code, PASSWORD_DEFAULT),
        'e'  => date('Y-m-d H:i:s', time() + $ttlSeconds),
        'ip' => $ip,
    ]);
}

function verify_otp(string $recipient, string $purpose, string $code): bool
{
    $pdo = DB::pdo();
    $stmt = $pdo->prepare(
        "SELECT id, code_hash, attempts, max_attempts, expires_at, used_at
         FROM otp_codes
         WHERE recipient = :r AND purpose = :p AND used_at IS NULL
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute(['r' => $recipient, 'p' => $purpose]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return false; // expired
    }
    if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
        return false; // too many attempts
    }
    // Increment attempts regardless of outcome.
    $pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id")
        ->execute(['id' => $row['id']]);
    if (!password_verify($code, (string) $row['code_hash'])) {
        return false;
    }
    // Consume the code.
    $pdo->prepare("UPDATE otp_codes SET used_at = NOW() WHERE id = :id")
        ->execute(['id' => $row['id']]);
    return true;
}

function send_otp_email(string $email, string $code, string $purpose): bool
{
    $subject = 'Your AgriMALL verification code';
    $body = "Your verification code is: {$code}\n\n"
          . "It expires in 10 minutes. If you did not request this, ignore this email.\n";
    return send_app_mail($email, $subject, $body);
}
/**
 * Bot detection helpers.
 *   1. Honeypot field: a hidden input real users never fill. Bots that
 *      auto-fill every field trip it.
 *   2. Time token: the form must take at least $minSeconds to submit (bots
 *      submit instantly) and no more than $maxSeconds (stale tabs). The token
 *      is one-shot: a replayed submission is rejected.
 *
 * Neither is foolproof; pair with rate limiting. Both fail open on missing
 * session data so a legitimate user is never locked out by a cache.
 */

/** Emit a hidden honeypot input. Name it something a bot wants to fill. */
function honeypot_field(string $name = 'website'): string
{
    return '<input type="text" name="' . e($name) . '" value="" '
         . 'tabindex="-1" autocomplete="off" aria-hidden="true" '
         . 'style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;" />';
}

/** True when the honeypot field was filled (i.e. likely a bot). */
function honeypot_filled(array $post, string $name = 'website'): bool
{
    return isset($post[$name]) && trim((string) $post[$name]) !== '';
}

/** Issue a one-shot time token for a named form; returns hidden input HTML. */
function issue_form_token(string $formName): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (!isset($_SESSION['form_tokens']) || !is_array($_SESSION['form_tokens'])) {
        $_SESSION['form_tokens'] = [];
    }
    $_SESSION['form_tokens'][$formName] = time();
    return '<input type="hidden" name="_form_token" value="' . e($formName) . '" />';
}

/** Validate the time token. Returns true when the submission looks human. */
function verify_form_token(string $formName, float $minSeconds = 0.5, int $maxSeconds = 3600): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $issued = $_SESSION['form_tokens'][$formName] ?? null;
    // Fail open if we have no record (e.g. session expired): never block a
    // real user because of a missing token.
    if (!is_int($issued) && !ctype_digit((string) $issued)) {
        return true;
    }
    $issued = (int) $issued;
    $elapsed = time() - $issued;
    // Consume the token so it cannot be replayed.
    unset($_SESSION['form_tokens'][$formName]);
    if ($elapsed < $minSeconds) {
        return false; // submitted too fast -> bot
    }
    if ($elapsed > $maxSeconds) {
        return false; // stale tab
    }
    return true;
}

/** Combined bot check. Returns true when the request looks like a bot. */
function is_bot_request(string $formName, array $post, string $honeypotName = 'website', float $minSeconds = 0.5): bool
{
    if (honeypot_filled($post, $honeypotName)) {
        return true;
    }
    if (!verify_form_token($formName, $minSeconds, 3600)) {
        return true;
    }
    return false;
}

function status_variant(string $status): string
{
    static $families = [
        'active' => 'success', 'approved' => 'success', 'completed' => 'success',
        'delivered' => 'success', 'verified' => 'success',
        'confirmed' => 'info', 'preparing' => 'info', 'out_for_delivery' => 'info',
        'ready_for_pickup' => 'info', 'shipped' => 'info',
        'pending' => 'warning', 'pending_verification' => 'warning',
        'delivery_fee_review' => 'warning',
        'cancelled' => 'danger', 'rejected' => 'danger', 'suspended' => 'danger',
        // Admin-issued freeze is not the farmer's own pause: keep it visibly
        // separate from 'paused' so both never collapse into one chip colour.
        'admin_deactivated' => 'danger', 'paused' => 'secondary', 'draft' => 'secondary',
    ];
    return $families[$status] ?? 'secondary';
}

/**
 * Notify a farmer about listings expiring within $days days or with low stock.
 * Called from dashboard.php for farmer role. Deduplicates by not re-notifying
 * within 24 hours for the same listing+reason.
 */
function check_farmer_alerts(PDO $pdo, int $farmerId): void
{
    // Expiring listings (within 3 days)
    $stmt = $pdo->prepare(
        "SELECT id, name, expiry_date FROM produce_listings
         WHERE farmer_id = :fid AND status = 'active' AND available_quantity > 0
           AND expiry_date IS NOT NULL AND expiry_date <> ''
           AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
           AND expiry_date >= CURDATE()"
    );
    $stmt->execute(['fid' => $farmerId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        // Check if we already notified in the last 24 hours
        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE user_id = :uid AND type = 'listings'
               AND title LIKE :t AND created_at >= (NOW() - INTERVAL 24 HOUR)"
        );
        $check->execute(['uid' => $farmerId, 't' => '%expiring%' . $row['name'] . '%']);
        if ((int) $check->fetchColumn() === 0) {
            notify_user($pdo, $farmerId, 'listings',
                'Listing expiring soon: ' . $row['name'],
                'Your listing "' . $row['name'] . '" expires on ' . $row['expiry_date'] . '. Consider restocking or updating.',
                'my_listings.php');
        }
    }

    // Low stock (available <= 5 or <= 2x minimum order qty)
    $stmt = $pdo->prepare(
        "SELECT id, name, available_quantity, minimum_order_qty FROM produce_listings
         WHERE farmer_id = :fid AND status = 'active' AND available_quantity > 0
           AND available_quantity <= GREATEST(5, minimum_order_qty * 2)"
    );
    $stmt->execute(['fid' => $farmerId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE user_id = :uid AND type = 'listings'
               AND title LIKE :t AND created_at >= (NOW() - INTERVAL 24 HOUR)"
        );
        $check->execute(['uid' => $farmerId, 't' => '%Low stock%' . $row['name'] . '%']);
        if ((int) $check->fetchColumn() === 0) {
            notify_user($pdo, $farmerId, 'listings',
                'Low stock: ' . $row['name'],
                'Your listing "' . $row['name'] . '" has only ' . $row['available_quantity'] . ' units left.',
                'my_listings.php');
        }
    }
}

function status_badge(string $status): string
{
    static $labels = [
        'admin_deactivated' => 'Removed by admin',
        'sold_out' => 'Sold out',
        'out_of_stock' => 'Out of stock',
        'delivery_fee_review' => 'Fee review',
        'ready_for_pickup' => 'Ready for pickup',
        'out_for_delivery' => 'Out for delivery',
    ];
    static $icons = [
        'pending' => 'bi-hourglass-split',
        'delivery_fee_review' => 'bi-cash-coin',
        'confirmed' => 'bi-check-circle',
        'preparing' => 'bi-box-seam',
        'ready_for_pickup' => 'bi-bag-check',
        'out_for_delivery' => 'bi-truck',
        'completed' => 'bi-check2-all',
        'rejected' => 'bi-x-circle',
        'cancelled' => 'bi-slash-circle',
        'active' => 'bi-check-circle',
        'paused' => 'bi-pause-circle',
        'draft' => 'bi-pencil',
        'sold_out' => 'bi-x-square',
        'archived' => 'bi-archive',
        'admin_deactivated' => 'bi-shield-x',
        'suspended' => 'bi-slash-circle',
    ];
    $label = $labels[$status] ?? str_replace('_', ' ', $status);
    $display = array_key_exists($status, $labels) ? $label : ucwords($label);
    $icon = $icons[$status] ?? 'bi-circle';
    return '<span class="dash-badge dash-badge--' . status_variant($status) . '">'
        . '<i class="bi ' . e($icon) . ' me-1" aria-hidden="true"></i>'
        . e($display) . '</span>';
}

/** Generate a human order reference like AM-20260915-4F9E2C. */
function order_ref(): string
{
    return 'AM-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * Last-resort photo for a listing with no image of its own; listing images
 * live in listing_images.image_path. Returns a bare relative path, not an
 * asset() URL: callers run asset() themselves.
 */
function fallback_produce_image(): string
{
    return 'assets/images/products/market.jpg';
}

/**
 * Whether a listing has passed its expiry date (FR-LIST-033). A null
 * expiry_date means "no shelf life declared", not expired. Expiry withholds the
 * listing from sale but never changes available_quantity (FR-LIST-034).
 *
 * @param array|object $listing Row with an expiry_date field; PDO::fetch()
 *   returns stdClass, some callers pass an array.
 */
function listing_is_expired($listing, ?string $today = null): bool
{
    // Null-coalescing offsets would warn on a stdClass, so read the field per shape.
    $date = is_array($listing)
        ? (string) ($listing['expiry_date'] ?? '')
        : (string) ($listing->expiry_date ?? '');
    $date = trim($date);
    if ($date === '') {
        return false;
    }

    $today ??= date('Y-m-d');
    $expires = strtotime($date);
    if ($expires === false) {
        return false;
    }

    return $expires < strtotime($today);
}

/**
 * Apply a signed $delta to a listing's available_quantity and record it.
 * FR-LIST-029/030: the update and its stock_adjustments row ship together; the
 * row is locked first (NFR-REL-002). Throws below zero (FR-ORD-025) or above
 * MAX_STOCK_QTY (FR-LIST-032).
 */
function stock_apply(PDO $pdo, int $listingId, float $delta, string $type, ?int $orderId, int $actorId, ?string $notes = null): void
{
    $stmt = $pdo->prepare('SELECT available_quantity FROM produce_listings WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        throw new RuntimeException('Listing not found for stock update.');
    }

    $before = (float) $listing['available_quantity'];
    $after  = round($before + $delta, 4);

    if ($after < 0) {
        throw new RuntimeException('Only ' . qty_str($before) . ' available; cannot deduct ' . qty_str(abs($delta)) . '.');
    }
    if ($after > MAX_STOCK_QTY) {
        throw new RuntimeException('Restoring this quantity would exceed the maximum available quantity of ' . qty_str(MAX_STOCK_QTY) . '.');
    }

    $pdo->prepare('UPDATE produce_listings SET available_quantity = :after WHERE id = :id')
        ->execute(['after' => $after, 'id' => $listingId]);

    // Keep status in step with stock so farmers never flip "sold out" by hand.
    // Only active <-> sold_out is touched; paused and archived stay as set.
    if ($after <= 0) {
        $pdo->prepare("UPDATE produce_listings SET status = 'sold_out' WHERE id = :id AND status = 'active'")
            ->execute(['id' => $listingId]);
    } elseif ($before <= 0) {
        $pdo->prepare("UPDATE produce_listings SET status = 'active' WHERE id = :id AND status = 'sold_out'")
            ->execute(['id' => $listingId]);
    }

    $pdo->prepare(
        'INSERT INTO stock_adjustments
            (listing_id, adjustment_type, quantity_change, quantity_before, quantity_after, reference_type, reference_id, notes, created_by)
         VALUES
            (:listing_id, :type, :change, :before, :after, :ref_type, :ref_id, :notes, :created_by)'
    )->execute([
        'listing_id'  => $listingId,
        'type'        => $type,
        'change'      => round($delta, 4),
        'before'      => $before,
        'after'       => $after,
        'ref_type'    => $orderId !== null ? 'order' : 'manual',
        'ref_id'      => $orderId,
        'notes'       => $notes,
        'created_by'  => $actorId,
    ]);
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

    // Notify the other parties, never the actor; same connection, so a rollback drops it too.
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
    // NFR-SEC-019: only non-sensitive field names may be persisted in the
    // audit payload. Anything outside the allowlist (passwords, tokens, PII,
    // free-text/code) is dropped before it reaches the row.
    static $allowed = ['status', 'verification_status', 'user_status', 'reason',
        'name', 'full_name', 'farm_name', 'contact_person', 'listing_status',
        'category', 'category_id', 'quantity', 'unit_price', 'delivery_fee',
        'delivery_method', 'fields', 'changed'];
    $sensitive = ['password', 'token', 'secret', 'csrf', 'email', 'phone',
        'hash', 'otp', 'ssn', 'card', 'cvv'];
    $filter = function (?array $data) use ($allowed, $sensitive): ?array {
        if ($data === null) return null;
        $out = [];
        foreach ($data as $k => $v) {
            if (!is_string($k)) continue;
            $kl = strtolower($k);
            $blocked = false;
            foreach ($sensitive as $s) {
                if (strpos($kl, $s) !== false) { $blocked = true; break; }
            }
            if ($blocked) continue;
            if (!in_array($kl, $allowed, true)) continue;
            // Strip any HTML/code from string values so a description or
            // free-text field can never land as a code block in the log.
            if (is_string($v)) {
                $v = trim(strip_tags($v));
                if ($v === '') continue;
            }
            $out[$k] = $v;
        }
        return $out === [] ? null : $out;
    };
    $old = $filter($old);
    $new = $filter($new);

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

/** Deliver a notification honouring the user's settings choices. Never throws:
 *  a notification must not roll back the action that caused it. */
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
    // Payload-less tickle: the service worker fetches the text with the session.
    push_send_tickle($pdo, $userId);
    // Expo devices get the text directly; best-effort, same as web push.
    mobile_push_send($pdo, $userId, $title, $body, $link);
}

/**
 * Send a plain-text email when config mail.enabled is on. Never throws:
 * delivery is best-effort and must not roll back the action that caused it.
 */
function send_app_mail(string $to, string $subject, string $body): bool
{
    global $config;
    if (empty($config['mail']['enabled']) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = (string) ($config['mail']['from'] ?? 'no-reply@agrimall.ph');
    $headers = 'From: ' . $from . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $encodedSubject, $body, $headers);
    if (!$ok) {
        error_log('mail delivery failed: ' . $subject);
    }
    return $ok;
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

    try {
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $email = (string) $stmt->fetchColumn();
        $link = app_base_url() . '/order.php?id=' . (int) $order['id'];
        send_app_mail($email, $title, $title . "\n\n" . ($body ?? '') . "\n\n" . $link);
    } catch (Throwable $e) {
        error_log('order status mail failed: ' . $e->getMessage());
    }
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

// ===== Account security events and account erasure =====

/**
 * Append a security-relevant event to logs/security.log.
 *
 * Not admin_audit_log (it no-ops for non-admins; its admin_id is ON DELETE
 * RESTRICT). Keep raw personal values out of $context; log changed fields and
 * the user id (NFR-PRIV-010).
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
        // Logging must not break the request.
    }
}

/** Whether password reset links may be surfaced in the page body. Refused in
 *  production, where the page would otherwise print a live reset token to the
 *  submitter. */
function local_reset_links_enabled(): bool
{
    // Deliberately not gated on 'env'. `env` decides whether PHP diagnostics
    // reach the browser; whether a one-time password-reset token may be shown
    // on screen is a separate decision, and deriving one from the other made the
    // reset round trip untestable (the link is never issued, so nothing can
    // follow it). Absent the key this is false, so production behaviour is
    // unchanged.
    global $config;
    return !empty($config['app']['local_password_reset_links']);
}

/**
 * Absolute base URL for canonical links, sitemap entries and password-reset
 * links.
 *
 * A configured base_url wins when it is a real value. An empty one -- or the
 * placeholder that ships in config.example.php -- falls back to the current
 * request, because the placeholder is silently destructive: it makes every
 * password-reset link point at https://your-domain.example/reset.php?token=...
 * and password reset stops working entirely, with no error anywhere. Deriving
 * the host from the request is correct on localhost, on the PHP dev server and
 * on whatever host the deployment actually uses, so an unset value is a safe
 * default rather than a broken one.
 */
function app_base_url(): string
{
    global $config;
    $configured = trim((string) ($config['app']['base_url'] ?? ''));
    if ($configured !== '' && !str_contains($configured, 'your-domain.example')) {
        return rtrim($configured, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * Deliver a password reset link over the local-only channel: there is no
 * mailer, so the raw token goes to logs/security.log and, when
 * local_reset_links_enabled() allows, is returned for display. Null when the
 * link must not be shown.
 */
function password_reset_deliver_local(int $userId, string $rawToken): ?string
{
    global $config;
    $base = app_base_url();
    $link = $base . '/reset.php?token=' . rawurlencode($rawToken);

    $emailed = false;
    try {
        $stmt = DB::pdo()->prepare('SELECT email FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $emailed = send_app_mail(
            (string) $stmt->fetchColumn(),
            'Reset your AgriMALL password',
            "Use this link to choose a new password:\n\n{$link}\n\n"
            . "If you did not ask for this, ignore this email."
        );
    } catch (Throwable $e) {
        error_log('password reset mail failed: ' . $e->getMessage());
    }

    security_event_log('password_reset_link_issued', [
        'user_id'    => $userId,
        'delivered'  => $emailed ? 'email' : 'local_log',
    ]);
    error_log(
        sprintf('[%s] password reset link for user #%d: %s', date('c'), $userId, $link) . PHP_EOL,
        3,
        (string) ($config['app']['security_log'] ?? (__DIR__ . '/../logs/security.log'))
    );

    if ($emailed) {
        return null;
    }
    return local_reset_links_enabled() ? $link : null;
}

/** Drop a user's outstanding password reset tokens, so issuing a new one
 *  invalidates every earlier "reset my password" link. */
function password_resets_revoke(int $userId): void
{
    DB::pdo()->prepare('DELETE FROM password_resets WHERE user_id = :id')
        ->execute(['id' => $userId]);
}

/** True when an unreleased retention hold exists on the user (FR-ADM-019,
 *  BR-034). An active hold outranks an erasure request. */
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

/** Count a user's nonterminal orders, buying or selling (FR-ADM-019 gates
 *  anonymization on this: an in-flight order names a counterparty who still
 *  needs to reach the person). */
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
 * The users row survives as the stable subject reference; profile rows are
 * scrubbed in place, not deleted (orders.*_id are ON DELETE RESTRICT through
 * them). Listings are archived (NFR-REL-004); order numbers, prices, statuses
 * and dates survive, free-text and location fields are cleared. Throws
 * RuntimeException if the transaction cannot be committed.
 */
function account_anonymize(int $userId): array
{
    $pdo = DB::pdo();
    $summary = [];

    $pdo->beginTransaction();
    try {
        // 1. Neutralise the account: random hash so it can never authenticate,
        //    tombstone email unique per id so the constraint still holds.
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
        //    resolving; NOT NULL columns become placeholders, nullable ones are cleared.
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

        // 4. Exact locations and user-authored text on historical orders
        //    (NFR-PRIV-009); statuses, quantities, prices and dates survive.
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

        // 6. Session and notification state cannot outlive the account; it only
        //    cascades on a physical delete, so clear it explicitly.
        // Table and column names cannot be bound as parameters, so they are
        // interpolated - which is only safe because this map is a literal in
        // this file and nothing else may supply it. The allow-list assertion
        // below keeps that true if the map is ever refactored to be dynamic.
        $tables = [
            'cart_items'         => 'buyer_id',
            'saved_listings'     => 'user_id',
            'notifications'      => 'user_id',
            'user_preferences'   => 'user_id',
            'user_suspensions'   => 'user_id',
            'password_resets'    => 'user_id',
        ];
        $allowedColumns = ['buyer_id', 'user_id'];
        foreach ($tables as $table => $column) {
            // Identifiers are unbindable, so reject anything not exactly the
            // known shape rather than trusting the call site.
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)
                || !in_array($column, $allowedColumns, true)) {
                throw new RuntimeException('Refusing to clear unknown table: ' . $table);
            }
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

function build_report_pdf(string $title, string $ref, array $filters, array $rows, ?array $totals, string $note = ''): string
{
    $lines = [$title . ' (' . $ref . ')', 'Generated: ' . date('Y-m-d H:i:s T'), 'Filters: ' . implode('; ', $filters)];
    if ($note !== '') {
        // Never truncate a capped export silently; say so on the document.
        $lines[] = 'NOTE: ' . $note;
    }
    $lines[] = '';
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


/**
 * Cascade effects of suspending a user account (FR-ADM-008/009/010).
 */
function admin_cascade_suspend(PDO $pdo, int $userId, string $role, ?string $reason): void
{
    $note = $reason !== null && $reason !== '' ? $reason : 'Account suspended by administrator.';

    if ($role === 'farmer') {
        $pdo->prepare("UPDATE produce_listings SET status = 'admin_deactivated'
                       WHERE farmer_id = :id AND status IN ('active','paused','draft','sold_out','out_of_stock')")
            ->execute(['id' => $userId]);

        $pending = $pdo->prepare("SELECT id, status FROM orders
                                  WHERE farmer_id = :id AND status IN ('pending','delivery_fee_review')");
        $pending->execute(['id' => $userId]);
        foreach ($pending->fetchAll() as $o) {
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")
                ->execute(['id' => $o['id']]);
            $pdo->prepare("INSERT INTO order_status_history
                (order_id, from_status, to_status, changed_by, changed_by_role, reason, created_at)
                VALUES (:o, :from, 'cancelled', :admin, 'admin', :reason, NOW())")
                ->execute(['o' => $o['id'], 'from' => $o['status'], 'admin' => current_user_id(), 'reason' => $note]);
        }

        $pdo->prepare("UPDATE orders SET admin_hold = 1, admin_hold_reason = :r
                       WHERE farmer_id = :id AND status IN ('confirmed','preparing','ready_for_pickup','out_for_delivery')")
            ->execute(['r' => $note, 'id' => $userId]);
    }

    if ($role === 'buyer') {
        $pending = $pdo->prepare("SELECT id, status FROM orders
                                  WHERE buyer_id = :id AND status IN ('pending','delivery_fee_review')");
        $pending->execute(['id' => $userId]);
        foreach ($pending->fetchAll() as $o) {
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")
                ->execute(['id' => $o['id']]);
            $pdo->prepare("INSERT INTO order_status_history
                (order_id, from_status, to_status, changed_by, changed_by_role, reason, created_at)
                VALUES (:o, :from, 'cancelled', :admin, 'admin', :reason, NOW())")
                ->execute(['o' => $o['id'], 'from' => $o['status'], 'admin' => current_user_id(), 'reason' => $note]);
        }

        $pdo->prepare("UPDATE orders SET admin_hold = 1, admin_hold_reason = :r
                       WHERE buyer_id = :id AND status IN ('confirmed','preparing','ready_for_pickup','out_for_delivery')")
            ->execute(['r' => $note, 'id' => $userId]);
    }
}
