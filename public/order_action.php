<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$orderId = (int) ($_POST['order_id'] ?? 0);
$action  = $_POST['action'] ?? '';
$reason  = trim((string) ($_POST['reason'] ?? ''));

if (!$orderId || !$action) {
    flash_set('error', 'Invalid request.');
    redirect_to('/dashboard.php');
}

if (mb_strlen($reason) > FIELD_LIMITS['reason']) {
    flash_set('error', 'Reason is too long (max ' . FIELD_LIMITS['reason'] . ' characters).');
    redirect_to('order.php?id=' . $orderId);
}

$pdo = DB::pdo();
$userId = current_user_id();
$role   = current_user_role();

try {
    $pdo->beginTransaction();

    // Lock the order row
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new RuntimeException('Order not found.');
    }

    $oldStatus = $order['status'];

    // Authorization + state machine rules
    switch ($action) {
        case 'confirm':
            // Farmer confirms order — sets delivery fee if farmer_delivery
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new RuntimeException('Only the assigned farmer can confirm this order.');
            }
            if (!in_array($oldStatus, ['pending', 'delivery_fee_review'], true)) {
                throw new RuntimeException('Order cannot be confirmed from status: ' . $oldStatus);
            }
            if ($order['delivery_method'] === 'farmer_delivery') {
                $fee = number_in($_POST['delivery_fee'] ?? null, 0, MAX_DELIVERY_FEE);
                if ($fee === null) {
                    throw new RuntimeException('Enter a delivery fee between 0 and ' . MAX_DELIVERY_FEE . '.');
                }
                $pdo->prepare('UPDATE orders SET delivery_fee = :fee WHERE id = :id')
                    ->execute(['fee' => $fee, 'id' => $orderId]);
            }
            $newStatus = 'confirmed';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, confirmed_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'ts' => $ts, 'id' => $orderId]);
            $logReason = 'Order confirmed by farmer.' . ($reason ? ' ' . $reason : '');
            break;

        case 'set_fee':
            // Farmer sets delivery fee while in delivery_fee_review
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new RuntimeException('Only the assigned farmer can set delivery fee.');
            }
            if ($oldStatus !== 'delivery_fee_review') {
                throw new RuntimeException('Fee can only be set in delivery_fee_review.');
            }
            $fee = number_in($_POST['delivery_fee'] ?? null, 0, MAX_DELIVERY_FEE);
            if ($fee === null) {
                throw new RuntimeException('Enter a delivery fee between 0 and ' . MAX_DELIVERY_FEE . '.');
            }
            $pdo->prepare('UPDATE orders SET delivery_fee = :fee WHERE id = :id')
                ->execute(['fee' => $fee, 'id' => $orderId]);
            // Remain in delivery_fee_review; buyer will be notified
            $newStatus = $oldStatus;
            $logReason = 'Delivery fee set to ' . money($fee) . '.' . ($reason ? ' ' . $reason : '');
            break;

        case 'reject':
            // Farmer rejects order (pending or delivery_fee_review)
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new RuntimeException('Only the assigned farmer can reject this order.');
            }
            if (!in_array($oldStatus, ['pending', 'delivery_fee_review'], true)) {
                throw new RuntimeException('Order cannot be rejected from status: ' . $oldStatus);
            }
            $newStatus = 'rejected';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, rejection_reason = :reason, rejected_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'reason' => $reason ?: 'No reason provided.', 'ts' => $ts, 'id' => $orderId]);
            // Restore stock
            $pdo->prepare('UPDATE produce_listings SET available_quantity = available_quantity + :qty WHERE id = :lid')
                ->execute(['qty' => $order['quantity'], 'lid' => $order['listing_id']]);
            $logReason = 'Order rejected by farmer: ' . ($reason ?: 'No reason provided.');
            break;

        case 'prepare':
            // Farmer starts preparing (confirmed -> preparing)
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new RuntimeException('Only the assigned farmer can prepare this order.');
            }
            if ($oldStatus !== 'confirmed') {
                throw new RuntimeException('Order must be confirmed before preparing.');
            }
            $newStatus = 'preparing';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, preparing_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'ts' => $ts, 'id' => $orderId]);
            $logReason = 'Farmer started preparing the order.' . ($reason ? ' ' . $reason : '');
            break;

        case 'ready':
            // Farmer marks ready (preparing -> ready_for_pickup OR out_for_delivery)
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new RuntimeException('Only the assigned farmer can mark this order ready.');
            }
            if ($oldStatus !== 'preparing') {
                throw new RuntimeException('Order must be in preparing state.');
            }
            if ($order['delivery_method'] === 'pickup') {
                $newStatus = 'ready_for_pickup';
                $ts = date('Y-m-d H:i:s');
                $pdo->prepare('UPDATE orders SET status = :status, ready_at = :ts WHERE id = :id')
                    ->execute(['status' => $newStatus, 'ts' => $ts, 'id' => $orderId]);
            } else {
                $newStatus = 'out_for_delivery';
                $ts = date('Y-m-d H:i:s');
                $pdo->prepare('UPDATE orders SET status = :status, out_for_delivery_at = :ts WHERE id = :id')
                    ->execute(['status' => $newStatus, 'ts' => $ts, 'id' => $orderId]);
            }
            $logReason = 'Order marked ready.' . ($reason ? ' ' . $reason : '');
            break;

        case 'ship':
            // Farmer marks out_for_delivery -> ship (alias) or admin/farmer moves ready_for_pickup -> out_for_delivery
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new RuntimeException('Only the assigned farmer can ship this order.');
            }
            if ($oldStatus !== 'ready_for_pickup' && $oldStatus !== 'out_for_delivery') {
                throw new RuntimeException('Order not ready to ship.');
            }
            $newStatus = 'out_for_delivery';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, out_for_delivery_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'ts' => $ts, 'id' => $orderId]);
            $logReason = 'Order shipped/out for delivery.' . ($reason ? ' ' . $reason : '');
            break;

        case 'complete':
            // Buyer or Farmer marks completed (depending on delivery_method)
            // For pickup: buyer completes when picked up
            // For delivery: farmer completes when delivered
            $canComplete = false;
            if ($order['delivery_method'] === 'pickup' && $role === 'buyer' && $order['buyer_id'] === $userId) {
                $canComplete = true;
            } elseif ($order['delivery_method'] === 'farmer_delivery' && $role === 'farmer' && $order['farmer_id'] === $userId) {
                $canComplete = true;
            }
            if (!$canComplete) {
                throw new RuntimeException('Only the buyer (pickup) or farmer (delivery) can complete this order.');
            }
            if (!in_array($oldStatus, ['ready_for_pickup', 'out_for_delivery'], true)) {
                throw new RuntimeException('Order must be ready or out for delivery to complete.');
            }
            $newStatus = 'completed';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, completed_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'ts' => $ts, 'id' => $orderId]);
            $logReason = 'Order completed.' . ($reason ? ' ' . $reason : '');
            break;

        case 'cancel':
            // Buyer cancels (pending/delivery_fee_review/confirmed/preparing)
            // Farmer cancels (confirmed/preparing) — restores stock if after confirm
            // Admin cancels (any)
            if ($role === 'buyer' && $order['buyer_id'] === $userId) {
                if (!in_array($oldStatus, ['pending', 'delivery_fee_review', 'confirmed', 'preparing'], true)) {
                    throw new RuntimeException('Buyer can only cancel before ready/shipped.');
                }
            } elseif ($role === 'farmer' && $order['farmer_id'] === $userId) {
                if (!in_array($oldStatus, ['confirmed', 'preparing'], true)) {
                    throw new RuntimeException('Farmer can only cancel after confirm, before ready.');
                }
            } elseif ($role === 'admin') {
                // Admin can cancel anything except completed/rejected/cancelled
                if (in_array($oldStatus, ['completed', 'rejected', 'cancelled'], true)) {
                    throw new RuntimeException('Cannot cancel order in status: ' . $oldStatus);
                }
            } else {
                throw new RuntimeException('Not authorized to cancel this order.');
            }
            $newStatus = 'cancelled';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, cancellation_reason = :reason, cancelled_by_role = :crole, cancelled_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'reason' => $reason ?: 'Cancelled.', 'crole' => $role, 'ts' => $ts, 'id' => $orderId]);
            // Restore stock if order was confirmed or later
            if (in_array($oldStatus, ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'], true)) {
                $pdo->prepare('UPDATE produce_listings SET available_quantity = available_quantity + :qty WHERE id = :lid')
                    ->execute(['qty' => $order['quantity'], 'lid' => $order['listing_id']]);
            }
            $logReason = 'Order cancelled by ' . $role . ': ' . ($reason ?: 'No reason provided.');
            break;

        default:
            throw new RuntimeException('Unknown action: ' . $action);
    }

    // Log status transition
    order_log_status($pdo, $orderId, $oldStatus, $newStatus, $userId, $role, $logReason);

    // Admin audit for sensitive actions
    if ($role === 'admin' || in_array($action, ['confirm', 'reject', 'cancel'], true)) {
        audit_log('order_' . $action, 'order', $orderId,
            ['status' => $oldStatus], ['status' => $newStatus]);
    }

    $pdo->commit();
    flash_set('success', 'Order status updated to ' . $newStatus . '.');
    redirect_to('/order.php?id=' . $orderId);

} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', $e->getMessage());
    redirect_to('/order.php?id=' . $orderId);
}