<?php
require __DIR__ . '/../_bootstrap.php';

$user = api_require_auth();
$pdo = DB::pdo();
$input = api_input();
$token = trim((string) ($input['token'] ?? ''));

if ($token === '') {
    api_error('Push token is required.');
}
// Only Expo-issued tokens; reject arbitrary URLs or long junk.
if (!preg_match('/^Expo(nent)?PushToken\[[A-Za-z0-9_\-]{10,200}\]$/', $token)) {
    api_error('Invalid push token.');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $pdo->prepare('DELETE FROM mobile_push_tokens WHERE token = :token AND user_id = :uid')
        ->execute(['token' => $token, 'uid' => (int) $user['id']]);
    api_ok(['message' => 'Push disabled.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$platform = ($input['platform'] ?? '') === 'ios' ? 'ios' : 'android';
$deviceName = mb_substr(trim((string) ($input['device_name'] ?? '')), 0, 100);

// A token belongs to one account at a time; reassign it on a shared device.
$pdo->prepare(
    'INSERT INTO mobile_push_tokens (user_id, token, platform, device_name, last_used_at)
     VALUES (:uid, :token, :platform, :device, NOW())
     ON DUPLICATE KEY UPDATE user_id = :uid2, platform = :platform2, device_name = :device2, last_used_at = NOW()'
)->execute([
    'uid' => (int) $user['id'],
    'token' => $token,
    'platform' => $platform,
    'device' => $deviceName ?: null,
    'uid2' => (int) $user['id'],
    'platform2' => $platform,
    'device2' => $deviceName ?: null,
]);

api_ok(['message' => 'Push enabled.']);
