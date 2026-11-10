<?php
require __DIR__ . '/../src/bootstrap.php';

// Device subscription endpoint for web push. The browser posts the subscription
// it got from the push service; the endpoint URL is the identity of that device.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function push_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!current_user_id()) {
    push_json(401, ['ok' => false, 'error' => 'login_required']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    push_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    push_json(403, ['ok' => false, 'error' => 'csrf']);
}
if (!push_enabled()) {
    push_json(503, ['ok' => false, 'error' => 'push_disabled']);
}

$userId = current_user_id();
$action = (string) ($_POST['action'] ?? 'subscribe');
$endpoint = trim((string) ($_POST['endpoint'] ?? ''));

if ($endpoint === '' || strlen($endpoint) > 500 || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
    push_json(422, ['ok' => false, 'error' => 'invalid_endpoint']);
}
$scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
$host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
$local = in_array($host, ['127.0.0.1', 'localhost'], true);
if ($scheme !== 'https' && !($scheme === 'http' && $local)) {
    push_json(422, ['ok' => false, 'error' => 'invalid_endpoint']);
}

$pdo = DB::pdo();

try {
    if ($action === 'unsubscribe') {
        $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = :user AND endpoint = :endpoint')
            ->execute(['user' => $userId, 'endpoint' => $endpoint]);
        push_json(200, ['ok' => true]);
    }

    $p256dh = trim((string) ($_POST['p256dh'] ?? ''));
    $auth = trim((string) ($_POST['auth'] ?? ''));
    // Both are base64url values from PushSubscription.toJSON(). Reject anything
    // that is not, rather than storing junk the sender can never use.
    $b64url = '/^[A-Za-z0-9_-]+$/';
    if (strlen($p256dh) < 40 || strlen($p256dh) > 200 || !preg_match($b64url, $p256dh)) {
        push_json(422, ['ok' => false, 'error' => 'invalid_key']);
    }
    if (strlen($auth) < 10 || strlen($auth) > 100 || !preg_match($b64url, $auth)) {
        push_json(422, ['ok' => false, 'error' => 'invalid_key']);
    }

    $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $pdo->prepare(
        'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
         VALUES (:user, :endpoint, :p256dh, :auth, :ua)
         ON DUPLICATE KEY UPDATE
             user_id = VALUES(user_id),
             p256dh = VALUES(p256dh),
             auth = VALUES(auth),
             user_agent = VALUES(user_agent)'
    )->execute([
        'user' => $userId,
        'endpoint' => $endpoint,
        'p256dh' => $p256dh,
        'auth' => $auth,
        'ua' => $ua,
    ]);

    push_json(200, ['ok' => true]);
} catch (Throwable $e) {
    error_log('push_subscribe failed: ' . $e->getMessage());
    push_json(500, ['ok' => false, 'error' => 'server_error']);
}
