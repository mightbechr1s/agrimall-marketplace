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
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
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
