<?php
require __DIR__ . '/../src/bootstrap.php';

// Reviews are only accepted for a completed order the buyer actually placed.
// One review per order is enforced by uq_review_order; the duplicate path is
// answered with a plain message instead of a raw SQL error.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$orderId = (int) ($_POST['order_id'] ?? 0);
$back = 'order.php?id=' . $orderId;

if (!current_user_id()) {
    redirect_to(login_url($back));
}
if (!in_array(current_user_role(), ['buyer', 'admin', 'manager'], true)) {
    flash_set('error', 'Only the buyer on an order can leave a review.');
    redirect_to($back);
}

$rating = (int) ($_POST['rating'] ?? 0);
if ($rating < 1 || $rating > 5) {
    flash_set('error', 'Choose a rating from 1 to 5 stars.');
    redirect_to($back);
}
$comment = trim((string) ($_POST['comment'] ?? ''));
if (mb_strlen($comment) > 1000) {
    flash_set('error', 'Keep the review under 1000 characters.');
    redirect_to($back);
}

$pdo = DB::pdo();
$stmt = $pdo->prepare(
    "SELECT id, farmer_id, buyer_id, status
       FROM orders
      WHERE id = :id AND buyer_id = :buyer"
);
$stmt->execute(['id' => $orderId, 'buyer' => current_user_id()]);
$order = $stmt->fetch();

if (!$order) {
    flash_set('error', 'Order not found.');
    redirect_to('orders.php');
}
if ($order['status'] !== 'completed') {
    flash_set('error', 'You can review a farmer once the order is completed.');
    redirect_to($back);
}

try {
    $pdo->prepare(
        'INSERT INTO farmer_reviews (order_id, farmer_id, buyer_id, rating, comment)
         VALUES (:order, :farmer, :buyer, :rating, :comment)'
    )->execute([
        'order'   => $orderId,
        'farmer'  => (int) $order['farmer_id'],
        'buyer'   => (int) $order['buyer_id'],
        'rating'  => $rating,
        'comment' => $comment !== '' ? $comment : null,
    ]);
} catch (PDOException $e) {
    if ((string) $e->getCode() === '23000') {
        flash_set('error', 'This order already has a review.');
        redirect_to($back);
    }
    throw $e;
}

try {
    notify_user(
        $pdo,
        (int) $order['farmer_id'],
        'orders',
        'New review received',
        $rating . '-star review on order #' . $orderId . '.',
        'profile.php?id=' . (int) $order['farmer_id']
    );
} catch (Throwable $e) {
    error_log('review notify failed: ' . $e->getMessage());
}

flash_set('success', 'Thanks — your review is posted.');
redirect_to($back);
