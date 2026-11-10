<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$input = api_input();
$email = trim((string) ($input['email'] ?? ''));
$code = trim((string) ($input['code'] ?? ''));
$fullName = mb_substr(trim((string) ($input['full_name'] ?? '')), 0, 100);
$password = (string) ($input['password'] ?? '');
$role = in_array($input['role'] ?? '', ['buyer', 'farmer'], true) ? (string) $input['role'] : 'buyer';

if ($email === '' || $code === '') {
    api_error('Email and code are required.');
}
if (!preg_match('/^\d{6}$/', $code)) {
    api_error('Invalid code format.');
}

// Verify OTP
$pdo = DB::pdo();
$stmt = $pdo->prepare(
    "SELECT id, code_hash, attempts, max_attempts, expires_at, used_at FROM otp_codes
     WHERE recipient = :r AND purpose = 'register' AND used_at IS NULL
     ORDER BY created_at DESC LIMIT 1"
);
$stmt->execute(['r' => $email]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    api_error('No pending verification. Request a new code.');
}
if (strtotime($row['expires_at']) < time()) {
    api_error('Code expired. Request a new one.');
}
if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
    api_error('Too many attempts. Request a new code.');
}

$pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id')->execute(['id' => $row['id']]);
if (!password_verify($code, $row['code_hash'])) {
    api_error('Invalid code.');
}

// Consume OTP
$pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = :id')->execute(['id' => $row['id']]);

// Create account
try {
    $pdo->beginTransaction();
    $status = $role === 'farmer' ? 'pending_verification' : 'active';
    $pdo->prepare('INSERT INTO users (email, password_hash, role, status) VALUES (:email, :hash, :role, :status)')
        ->execute(['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'status' => $status]);
    $userId = (int) $pdo->lastInsertId();

    if (in_array($role, ['buyer', 'admin', 'manager'], true)) {
        $pdo->prepare('INSERT INTO buyer_profiles (user_id, full_name) VALUES (:uid, :name)')
            ->execute(['uid' => $userId, 'name' => $fullName]);
    }
    if ($role === 'farmer') {
        $pdo->prepare(
            'INSERT INTO farmer_profiles (user_id, farm_name, contact_person, contact_phone, contact_email, address, barangay, municipality_city, province, region, verification_status) VALUES (:uid, :farm, :person, "", :email, "", "", "", "", "", "pending")'
        )->execute(['uid' => $userId, 'farm' => $fullName . "'s Farm", 'person' => $fullName, 'email' => $email]);
    }

    // Create API token
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, DATE_ADD(NOW(), INTERVAL 30 DAY))')
        ->execute(['uid' => $userId, 'hash' => password_hash($token, PASSWORD_DEFAULT)]);

    $pdo->commit();

    $bearer = base64_encode($userId . ':' . $role . ':' . $token);
    api_ok([
        'token' => $bearer,
        'user' => ['id' => $userId, 'email' => $email, 'role' => $role],
        'message' => 'Account created successfully.',
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('register_verify: ' . $e->getMessage());
    api_error('Registration failed. Please try again.', 500);
}
