<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
$i18nAll = include __DIR__ . '/../src/i18n_copy.php';
$ordersCopy = $i18nAll['orders'] ?? [];
$listingActionsCopy = $i18nAll['listing_actions'] ?? [];
$adminVerifyCopy = $i18nAll['admin_verify'] ?? [];
$cartCopy = $i18nAll['cart'] ?? [];

$orderId = (int) ($_POST['order_id'] ?? 0);
$action  = $_POST['action'] ?? '';
$reason  = trim((string) ($_POST['reason'] ?? ''));

if (!$orderId || !$action) {
    flash_set('error', t($ordersCopy, 'invalid_request'));
    redirect_to('/dashboard.php');
}

if (mb_strlen($reason) > FIELD_LIMITS['reason']) {
    flash_set('error', sprintf(t($ordersCopy, 'reason_too_long'), FIELD_LIMITS['reason']));
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
        throw new UserFacingException(t($ordersCopy, 'order_not_found'));
    }

    $oldStatus = $order['status'];

    // Authorization + state machine rules
    switch ($action) {
        case 'confirm':
            // Farmer confirms order — sets delivery fee if farmer_delivery
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new UserFacingException(t($ordersCopy, 'farmer_only_confirm'));
            }
            if (!in_array($oldStatus, ['pending', 'delivery_fee_review'], true)) {
                throw new UserFacingException(sprintf(t($ordersCopy, 'cannot_confirm_status'), $oldStatus));
            }
            // Re-read and lock the listing: pending orders can outlive expiry (FR-LIST-033).
            $listingStmt = $pdo->prepare('SELECT * FROM produce_listings WHERE id = :id FOR UPDATE');
            $listingStmt->execute(['id' => (int) $order['listing_id']]);
            $listing = $listingStmt->fetch();
            if (!$listing) {
                throw new UserFacingException(t($ordersCopy, 'listing_gone'));
            }
            if (listing_is_expired($listing)) {
                throw new UserFacingException(t($ordersCopy, 'listing_expired'));
            }
            if ($order['delivery_method'] === 'farmer_delivery') {
                $fee = number_in($_POST['delivery_fee'] ?? null, 0, MAX_DELIVERY_FEE);
                if ($fee === null) {
                    throw new UserFacingException(sprintf(t($ordersCopy, 'fee_range'), MAX_DELIVERY_FEE));
                }
                $pdo->prepare('UPDATE orders SET delivery_fee = :fee WHERE id = :id')
                    ->execute(['fee' => $fee, 'id' => $orderId]);
            }
            $newStatus = 'confirmed';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, confirmed_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'ts' => $ts, 'id' => $orderId]);
            // Stock deducted only here, on confirmation (FR-ORD-010, BR-009).
            // Pending / delivery_fee_review reserves nothing (BR-008).
            stock_apply($pdo, (int) $order['listing_id'], -(float) $order['quantity'],
                'sale_deduction', $orderId, $userId,
                'Order ' . ($order['order_number']) . ' confirmed.');
            $logReason = 'Order confirmed by farmer.' . ($reason ? ' ' . $reason : '');
            break;

        case 'set_fee':
            // Farmer sets delivery fee while in delivery_fee_review
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new UserFacingException(t($ordersCopy, 'farmer_only_fee'));
            }
            if ($oldStatus !== 'delivery_fee_review') {
                throw new UserFacingException(t($ordersCopy, 'fee_wrong_status'));
            }
            $fee = number_in($_POST['delivery_fee'] ?? null, 0, MAX_DELIVERY_FEE);
            if ($fee === null) {
                throw new UserFacingException(sprintf(t($ordersCopy, 'fee_range'), MAX_DELIVERY_FEE));
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
                throw new UserFacingException(t($ordersCopy, 'farmer_only_reject'));
            }
            if (!in_array($oldStatus, ['pending', 'delivery_fee_review'], true)) {
                throw new UserFacingException(sprintf(t($ordersCopy, 'cannot_reject_status'), $oldStatus));
            }
            $newStatus = 'rejected';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, rejection_reason = :reason, rejected_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'reason' => $reason ?: 'No reason provided.', 'ts' => $ts, 'id' => $orderId]);
            // No stock change on rejection: reject only happens from pending or
            // delivery_fee_review, which never deducted stock (FR-ORD-008, FR-ORD-023, BR-008).
            $logReason = 'Order rejected by farmer: ' . ($reason ?: 'No reason provided.');
            break;

        case 'prepare':
            // Farmer starts preparing (confirmed -> preparing)
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new UserFacingException(t($ordersCopy, 'farmer_only_prepare'));
            }
            if ($oldStatus !== 'confirmed') {
                throw new UserFacingException(t($ordersCopy, 'must_confirm_first'));
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
                throw new UserFacingException(t($ordersCopy, 'farmer_only_ready'));
            }
            if ($oldStatus !== 'preparing') {
                throw new UserFacingException(t($ordersCopy, 'must_be_preparing'));
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
                throw new UserFacingException(t($ordersCopy, 'farmer_only_ship'));
            }
            if ($oldStatus !== 'ready_for_pickup' && $oldStatus !== 'out_for_delivery') {
                throw new UserFacingException(t($ordersCopy, 'not_ready_to_ship'));
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
                throw new UserFacingException(t($ordersCopy, 'wrong_completer'));
            }
            if (!in_array($oldStatus, ['ready_for_pickup', 'out_for_delivery'], true)) {
                throw new UserFacingException(t($ordersCopy, 'must_be_ready_ship'));
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
                    throw new UserFacingException(t($ordersCopy, 'buyer_cancel_window'));
                }
            } elseif ($role === 'farmer' && $order['farmer_id'] === $userId) {
                if (!in_array($oldStatus, ['confirmed', 'preparing'], true)) {
                    throw new UserFacingException(t($ordersCopy, 'farmer_cancel_window'));
                }
            } elseif ($role === 'admin') {
                // Admin can cancel anything except completed/rejected/cancelled
                if (in_array($oldStatus, ['completed', 'rejected', 'cancelled'], true)) {
                    throw new UserFacingException(sprintf(t($ordersCopy, 'cannot_cancel_status'), $oldStatus));
                }
            } else {
                throw new UserFacingException(t($ordersCopy, 'not_authorized_cancel'));
            }
            $newStatus = 'cancelled';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, cancellation_reason = :reason, cancelled_by_role = :crole, cancelled_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'reason' => $reason ?: 'Cancelled.', 'crole' => $role, 'ts' => $ts, 'id' => $orderId]);
            // Restore stock only if the order was confirmed or later, since
            // confirmation is where the quantity was deducted (BR-036).
            if (in_array($oldStatus, ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'], true)) {
                stock_apply($pdo, (int) $order['listing_id'], (float) $order['quantity'],
                    'return', $orderId, $userId,
                    'Stock restored: order ' . ($order['order_number']) . ' cancelled by ' . $role . '.');
            }
            $logReason = 'Order cancelled by ' . $role . ': ' . ($reason ?: 'No reason provided.');
            break;

        case 'release_hold':
            if ($role !== 'admin') {
                throw new UserFacingException(t($ordersCopy, 'admin_only') ?? 'Admin only.');
            }
            if (empty($order['admin_hold'])) {
                throw new UserFacingException('Order is not on hold.');
            }
            $pdo->prepare('UPDATE orders SET admin_hold = 0, admin_hold_reason = NULL WHERE id = :id')
                ->execute(['id' => $orderId]);
            $newStatus = $oldStatus;
            $logReason = 'Administrative hold released by admin.';
            break;

        case 'cancel_held':
            if ($role !== 'admin') {
                throw new UserFacingException(t($ordersCopy, 'admin_only') ?? 'Admin only.');
            }
            if (empty($order['admin_hold'])) {
                throw new UserFacingException('Order is not on hold.');
            }
            if (in_array($oldStatus, ['completed', 'rejected', 'cancelled'], true)) {
                throw new UserFacingException(sprintf(t($ordersCopy, 'cannot_cancel_status'), $oldStatus));
            }
            $restore = !empty($_POST['restore_stock']);
            $newStatus = 'cancelled';
            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE orders SET status = :status, admin_hold = 0, admin_hold_reason = NULL, cancellation_reason = :reason, cancelled_by_role = :crole, cancelled_at = :ts WHERE id = :id')
                ->execute(['status' => $newStatus, 'reason' => $reason ?: 'Cancelled by administrator.', 'crole' => 'admin', 'ts' => $ts, 'id' => $orderId]);
            if ($restore && in_array($oldStatus, ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'], true)) {
                stock_apply($pdo, (int) $order['listing_id'], (float) $order['quantity'],
                    'return', $orderId, $userId,
                    'Stock restored: held order ' . ($order['order_number']) . ' cancelled by admin.');
            }
            $logReason = 'Held order cancelled by admin' . ($restore ? ' (stock restored)' : ' (stock not restored)') . ': ' . ($reason ?: 'No reason provided.');
            break;

        default:
            throw new UserFacingException(sprintf(t($ordersCopy, 'unknown_action'), $action));
    }

    // Log status transition
    order_log_status($pdo, $orderId, $oldStatus, $newStatus, $userId, $role, $logReason);

    // Admin audit for sensitive actions
    if ($role === 'admin' || in_array($action, ['confirm', 'reject', 'cancel'], true)) {
        audit_log('order_' . $action, 'order', $orderId,
            ['status' => $oldStatus], ['status' => $newStatus]);
    }

    $pdo->commit();
    flash_set('success', sprintf(t($ordersCopy, 'status_updated'), $newStatus));
    redirect_to('/order.php?id=' . $orderId);

} catch (UserFacingException $e) {
    $pdo->rollBack();
    flash_set('error', $e->getMessage());
    redirect_to('/order.php?id=' . $orderId);
} catch (Throwable $e) {
    // Unexpected: its text can name tables, columns or SQL, so log it and
    // show only a reference the user can quote to support.
    $pdo->rollBack();
    flash_set('error', generic_error_message(app_log_exception($e, 'order_action')));
    redirect_to('/order.php?id=' . $orderId);
}