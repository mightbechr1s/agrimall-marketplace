<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$input = api_input();
$fullName = mb_substr(trim((string) ($input['full_name'] ?? '')), 0, 100);
$email = mb_substr(trim((string) ($input['email'] ?? '')), 0, 255);
$password = (string) ($input['password'] ?? '');
$role = in_array($input['role'] ?? '', ['buyer', 'farmer'], true) ? (string) $input['role'] : 'buyer';

if ($fullName === '') api_error('Full name is required.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) api_error('Valid email is required.');
if (strlen($password) < 8) api_error('Password must be at least 8 characters.');

$pdo = DB::pdo();
$check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$check->execute(['email' => $email]);
if ($check->fetch()) {
    api_error('Email already registered.');
}

// Generate and store OTP
$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$pdo->prepare(
    "INSERT INTO otp_codes (recipient, channel, purpose, code_hash, expires_at) VALUES (:r, 'email', 'register', :h, DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
)->execute(['r' => $email, 'h' => password_hash($code, PASSWORD_DEFAULT)]);

// Send email (best effort)
send_app_mail($email, 'Your AgriMALL verification code', "Your code: {$code}\nExpires in 10 minutes.");

// Store pending registration in session (for web) or return for mobile
api_ok([
    'message' => 'Verification code sent to ' . $email,
    'email' => $email,
    // In dev (mail off), include the code for testing
    'dev_code' => (empty($GLOBALS['config']['mail']['enabled']) ? $code : null),
]);
