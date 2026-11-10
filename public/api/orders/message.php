<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
$input = api_input();
$orderId = (int) ($input['order_id'] ?? 0);
$body = trim((string) ($input['body'] ?? ''));

if ($orderId <= 0) {
    api_error('Invalid order ID.');
}
if ($body === '') {
    api_error('Message cannot be empty.');
}
if (mb_strlen($body) > 2000) {
    api_error('Message too long.');
}

$pdo = DB::pdo();

// Verify access
$stmt = $pdo->prepare('SELECT buyer_id, farmer_id FROM v_order_summary WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    api_error('Order not found.', 404);
}

$role = $user['role'];
$userId = $user['id'];
$canMessage = false;
if ($role === 'buyer' && (int) $order['buyer_id'] === $userId) $canMessage = true;
if (in_array($role, ['farmer', 'admin', 'manager'], true) && (int) $order['farmer_id'] === $userId) $canMessage = true;
if ($role === 'admin' || $role === 'manager') $canMessage = true;

if (!$canMessage) {
    api_error('Forbidden.', 403);
}

$senderRole = in_array($role, ['farmer', 'admin', 'manager'], true) ? 'farmer' : 'buyer';
// Admins/managers sending as admin
if ($role === 'admin' || $role === 'manager') $senderRole = 'admin';

$pdo->prepare(
    'INSERT INTO order_messages (order_id, sender_id, sender_role, body, created_at) VALUES (:oid, :sid, :role, :body, NOW())'
)->execute([
    'oid' => $orderId,
    'sid' => $userId,
    'role' => $senderRole,
    'body' => $body,
]);

api_ok(['message' => 'Message sent.']);
