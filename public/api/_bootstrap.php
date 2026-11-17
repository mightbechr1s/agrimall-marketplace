<?php
// API bootstrap: JSON responses, CORS, auth token handling.

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:8081', 'http://localhost:19006', 'http://127.0.0.1:8081', 'http://127.0.0.1:8000'];
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function api_input(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    $cached = is_array($data) ? $data : [];
    return $cached;
}

function api_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function api_ok(array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error(string $message, int $status = 400, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_auth_user(): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        if (current_user_id()) {
            return ['id' => current_user_id(), 'role' => current_user_role()];
        }
        return null;
    }
    $token = $m[1];
    $parts = explode(':', base64_decode($token), 3);
    if (count($parts) !== 3) return null;
    // The middle segment is client-supplied and only kept for compatibility.
    // Never trust it: look up the real role and status from users.
    [$userId, , $rawToken] = $parts;
    $userId = (int) $userId;
    if ($userId <= 0) return null;

    $pdo = DB::pdo();
    $stmt = $pdo->prepare(
        'SELECT t.id, t.token_hash, u.role, u.status
         FROM api_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.user_id = :uid AND t.expires_at > NOW()
         ORDER BY t.created_at DESC'
    );
    $stmt->execute(['uid' => $userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (password_verify($rawToken, $row['token_hash'])) {
            if (($row['status'] ?? '') !== 'active') {
                return null;
            }
            $pdo->prepare('UPDATE api_tokens SET last_used = NOW() WHERE id = :id')->execute(['id' => $row['id']]);
            return ['id' => $userId, 'role' => $row['role']];
        }
    }
    return null;
}

function api_require_auth(): array
{
    $user = api_auth_user();
    if (!$user) {
        api_error('Authentication required.', 401);
    }
    return $user;
}

// ---- API rate limiting ----
// [URL path prefix, category, max attempts, window seconds, fail closed, also key by email]
// Fail closed = reject when the rate-limit table is unavailable (auth endpoints).
// Fail open   = let the request through (write endpoints).
$apiRateLimitRules = [
    ['/api/auth/login',       'auth_login',   5, 900,  true,  true],   // 5 per 15 min, IP + email
    ['/api/auth/register',    'auth_register', 3, 900,  true,  true],   // 3 per 15 min, IP + email
    ['/api/auth/verify-otp',  'auth_otp',      5, 900,  true,  true],   // 5 per 15 min, IP + email
];

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$ip = api_client_ip();

foreach ($apiRateLimitRules as [$prefix, $category, $maxAttempts, $windowSeconds, $failClosed, $keyByEmail]) {
    if (!str_starts_with($requestUri, $prefix)) {
        continue;
    }

    try {
        // Per-IP limit.
        $retryAfter = api_rate_limit($category, $ip, $maxAttempts, $windowSeconds);
        if ($retryAfter > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            echo json_encode([
                'ok' => false,
                'error' => 'Too many requests. Try again later.',
                'retry_after' => $retryAfter,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Optional per-account limit (email from the request body).
        if ($keyByEmail) {
            $input = api_input();
            $email = trim((string) ($input['email'] ?? ''));
            if ($email !== '') {
                $retryAfter = api_rate_limit($category . '_email', $ip . ':' . $email, $maxAttempts, $windowSeconds);
                if ($retryAfter > 0) {
                    http_response_code(429);
                    header('Retry-After: ' . $retryAfter);
                    echo json_encode([
                        'ok' => false,
                        'error' => 'Too many requests for this account. Try again later.',
                        'retry_after' => $retryAfter,
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }
    } catch (Throwable $e) {
        app_log_exception($e, 'api_rate_limit:' . $category);
        if ($failClosed) {
            api_error('Authentication temporarily unavailable.', 503);
        }
        // Fail open: let non-auth requests through.
    }
    break; // only the first matching rule applies
}
