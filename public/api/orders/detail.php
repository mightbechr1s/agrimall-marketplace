<?php
require __DIR__ . '/../_bootstrap.php';

$pdo = DB::pdo();
$user = api_require_auth();
$orderId = (int) ($_GET['id'] ?? 0);

if ($orderId <= 0) {
    api_error('Invalid order ID.');
}

$stmt = $pdo->prepare('SELECT * FROM v_order_summary WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    api_error('Order not found.', 404);
}

// Access check
$role = $user['role'];
$userId = $user['id'];
if ($role === 'buyer' && (int) $order['buyer_id'] !== $userId) {
    api_error('Forbidden.', 403);
}
if (in_array($role, ['farmer', 'admin', 'manager'], true) && (int) $order['farmer_id'] !== $userId && $role !== 'admin' && $role !== 'manager') {
    api_error('Forbidden.', 403);
}

// Status history
$histStmt = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = :id ORDER BY created_at ASC');
$histStmt->execute(['id' => $orderId]);
$history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

// Messages
$msgStmt = $pdo->prepare('SELECT id, sender_id, sender_role, body, read_at, created_at FROM order_messages WHERE order_id = :id ORDER BY created_at ASC');
$msgStmt->execute(['id' => $orderId]);
$messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

// Mark messages as read
$pdo->prepare('UPDATE order_messages SET read_at = NOW() WHERE order_id = :id AND sender_id <> :me AND read_at IS NULL')
    ->execute(['id' => $orderId, 'me' => $userId]);

api_ok([
    'order' => [
        'id' => (int) $order['id'],
        'order_number' => $order['order_number'],
        'status' => $order['status'],
        'created_at' => $order['created_at'],
        'listing_id' => (int) $order['listing_id'],
        'listing_name' => $order['listing_name'],
        'quantity' => (float) $order['quantity'],
        'unit_price' => (float) $order['unit_price'],
        'unit' => $order['unit_symbol'],
        'total' => (float) $order['unit_price'] * (float) $order['quantity'] + (float) $order['delivery_fee'],
        'delivery_method' => $order['delivery_method'],
        'delivery_fee' => (float) $order['delivery_fee'],
        'delivery_address' => $order['delivery_address'],
        'preferred_pickup_date' => $order['preferred_pickup_date'],
        'preferred_pickup_time' => $order['preferred_pickup_time'],
        'buyer_note' => $order['buyer_note'],
        'farmer_note' => $order['farmer_note'],
        'buyer_name' => $order['buyer_name'],
        'farm_name' => $order['farm_name'],
        'buyer_id' => (int) $order['buyer_id'],
        'farmer_id' => (int) $order['farmer_id'],
    ],
    'history' => array_map(function ($h) {
        return [
            'status' => $h['to_status'],
            'created_at' => $h['created_at'],
            'note' => $h['reason'] ?? null,
        ];
    }, $history),
    'messages' => array_map(function ($m) {
        return [
            'id' => (int) $m['id'],
            'sender_id' => (int) $m['sender_id'],
            'sender_role' => $m['sender_role'],
            'body' => $m['body'],
            'created_at' => $m['created_at'],
            'read_at' => $m['read_at'],
        ];
    }, $messages),
]);
