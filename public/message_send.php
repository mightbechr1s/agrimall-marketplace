<?php
require __DIR__ . '/../src/bootstrap.php';

// Order-scoped message. The order row is the access check: only its buyer and
// its farmer may post, and the thread cannot be reached any other way.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$orderId = (int) ($_POST['order_id'] ?? 0);
$back = 'order.php?id=' . $orderId . '#messages';

if (!current_user_id()) {
    redirect_to(login_url($back));
}

$role = current_user_role();
if (!in_array($role, ['buyer', 'farmer'], true)) {
    flash_set('error', 'Only the buyer and the farmer on an order can message each other.');
    redirect_to($back);
}

$body = trim((string) ($_POST['body'] ?? ''));
if ($body === '') {
    flash_set('error', 'Write a message first.');
    redirect_to($back);
}
if (mb_strlen($body) > 2000) {
    flash_set('error', 'Keep messages under 2000 characters.');
    redirect_to($back);
}

$pdo = DB::pdo();
$stmt = $pdo->prepare('SELECT id, buyer_id, farmer_id, order_number FROM orders WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

$isBuyer = $order && (int) $order['buyer_id'] === current_user_id();
$isFarmer = $order && (int) $order['farmer_id'] === current_user_id();
if (!$order || (!$isBuyer && !$isFarmer)) {
    flash_set('error', 'Order not found.');
    redirect_to('orders.php');
}

try {
    $pdo->prepare(
        'INSERT INTO order_messages (order_id, sender_id, sender_role, body)
         VALUES (:order, :sender, :role, :body)'
    )->execute([
        'order'  => $orderId,
        'sender' => current_user_id(),
        'role'   => $role,
        'body'   => $body,
    ]);

    $recipient = $isBuyer ? (int) $order['farmer_id'] : (int) $order['buyer_id'];
    notify_user(
        $pdo,
        $recipient,
        'messages',
        'New message on order ' . $order['order_number'],
        mb_strimwidth($body, 0, 120, '…'),
        $back
    );
} catch (Throwable $e) {
    flash_set('error', generic_error_message(app_log_exception($e, 'message_send')));
}

redirect_to($back);
