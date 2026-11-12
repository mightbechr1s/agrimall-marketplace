<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$input = api_input();
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    api_error('Email and password are required.');
}

$stmt = DB::pdo()->prepare('SELECT id, password_hash, role, status, totp_secret, totp_enabled FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    api_error('Invalid credentials.', 401);
}
if ($user['status'] !== 'active') {
    api_error('Account is not active.', 403);
}
// Same second step as the web login: an account with 2FA on cannot mint a
// bearer token from a password alone. Clients pass totp_code in the body; a
// one-time recovery code is accepted in the same field.
if ((int) ($user['totp_enabled'] ?? 0) === 1) {
    $code = (string) ($input['totp_code'] ?? '');
    if (!totp_verify(sensitive_decrypt((string) ($user['totp_secret'] ?? '')), $code)
        && !recovery_code_verify((int) $user['id'], $code)) {
        api_error('Two-factor code required or invalid.', 401, ['totp_required' => true]);
    }
}

$token = bin2hex(random_bytes(32));
$tokenHash = password_hash($token, PASSWORD_DEFAULT);
$pdo = DB::pdo();
$pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, device_name, expires_at) VALUES (:uid, :hash, :dev, DATE_ADD(NOW(), INTERVAL 30 DAY))')
    ->execute([
        'uid' => $user['id'],
        'hash' => $tokenHash,
        'dev' => $input['device_name'] ?? null,
    ]);

// Bearer token: base64(user_id:role:raw_token)
$bearer = base64_encode($user['id'] . ':' . $user['role'] . ':' . $token);

api_ok([
    'token' => $bearer,
    'user' => [
        'id' => (int) $user['id'],
        'email' => $email,
        'role' => $user['role'],
    ],
]);
