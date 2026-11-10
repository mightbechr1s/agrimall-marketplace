<?php
/**
 * Order state machine shared by the web form (public/order_action.php) and the
 * JSON API (public/api/orders/action.php).
 *
 * The caller must already be inside a transaction and hold a row lock on the
 * order (SELECT ... FOR UPDATE). This function only mutates the order, stock
 * and related rows; logging, auditing and commit stay with the caller.
 *
 * @param array  $order  Locked orders row.
 * @param string $action confirm|set_fee|reject|prepare|ready|ship|complete|cancel|release_hold|cancel_held
 * @param array  $input  Raw request input (delivery_fee, restore_stock).
 * @param array  $copy   i18n "orders" copy array.
 * @return array{status:string,reason:string,old:string}
 */
function order_apply_action(PDO $pdo, array $order, string $action, string $role, int $userId, array $input, string $reason, array $copy = []): array
{
    $orderId = (int) $order['id'];
    $oldStatus = $order['status'];

    switch ($action) {
        case 'confirm':
            // Farmer confirms order — sets delivery fee if farmer_delivery
            if ($role !== 'farmer' || $order['farmer_id'] !== $userId) {
                throw new UserFacingException(t($copy, 'farmer_only_confirm'));
            }
            if (!in_array($oldStatus, ['pending', 'delivery_fee_review'], true)) {
                throw new UserFacingException(sprintf(t($copy, 'cannot_confirm_status'), $oldStatus));
            }
            // Re-read and lock the listing: pending orders can outlive expiry (FR-LIST-033).
            $listingStmt = $pdo->prepare('SELECT * FROM produce_listings WHERE id = :id FOR UPDATE');
            $listingStmt->execute(['id' => (int) $order['listing_id']]);
            $listing = $listingStmt->fetch();
            if (!$listing) {
                throw new UserFacingException(t($copy, 'listing_gone'));
            }
            if (listing_is_expired($listing)) {
                throw new UserFacingException(t($copy, 'listing_expired'));
            }
            if ($order['delivery_method'] === 'farmer_delivery') {
                $fee = number_in($input['delivery_fee'] ?? null, 0, MAX_DELIVERY_FEE);
                if ($fee === null) {
                    throw new UserFacingException(sprintf(t($copy, 'fee_range'), MAX_DELIVERY_FEE));
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
                throw new UserFacingException(t($copy, 'farmer_only_fee'));
            }
            if ($oldStatus !== 'delivery_fee_review') {
                throw new UserFacingException(t($copy, 'fee_wrong_status'));
            }
            $fee = number_in($input['delivery_fee'] ?? null, 0, MAX_DELIVERY_FEE);
            if ($fee === null) {
                throw new UserFacingException(sprintf(t($copy, 'fee_range'), MAX_DELIVERY_FEE));
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
                throw new UserFacingException(t($copy, 'farmer_only_reject'));
            }
            if (!in_array($oldStatus, ['pending', 'delivery_fee_review'], true)) {
                throw new UserFacingException(sprintf(t($copy, 'cannot_reject_status'), $oldStatus));
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
                throw new UserFacingException(t($copy, 'farmer_only_prepare'));
            }
            if ($oldStatus !== 'confirmed') {
                throw new UserFacingException(t($copy, 'must_confirm_first'));
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
                throw new UserFacingException(t($copy, 'farmer_only_ready'));
            }
            if ($oldStatus !== 'preparing') {
                throw new UserFacingException(t($copy, 'must_be_preparing'));
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
                throw new UserFacingException(t($copy, 'farmer_only_ship'));
            }
            if ($oldStatus !== 'ready_for_pickup' && $oldStatus !== 'out_for_delivery') {
                throw new UserFacingException(t($copy, 'not_ready_to_ship'));
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
                throw new UserFacingException(t($copy, 'wrong_completer'));
            }
            if (!in_array($oldStatus, ['ready_for_pickup', 'out_for_delivery'], true)) {
                throw new UserFacingException(t($copy, 'must_be_ready_ship'));
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
                    throw new UserFacingException(t($copy, 'buyer_cancel_window'));
                }
            } elseif ($role === 'farmer' && $order['farmer_id'] === $userId) {
                if (!in_array($oldStatus, ['confirmed', 'preparing'], true)) {
                    throw new UserFacingException(t($copy, 'farmer_cancel_window'));
                }
            } elseif (in_array($role, ['admin', 'manager'], true)) {
                // Admin/Manager can cancel anything except completed/rejected/cancelled
                if (in_array($oldStatus, ['completed', 'rejected', 'cancelled'], true)) {
                    throw new UserFacingException(sprintf(t($copy, 'cannot_cancel_status'), $oldStatus));
                }
            } else {
                throw new UserFacingException(t($copy, 'not_authorized_cancel'));
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
                throw new UserFacingException(t($copy, 'admin_only') ?? 'Admin only.');
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
                throw new UserFacingException(t($copy, 'admin_only') ?? 'Admin only.');
            }
            if (empty($order['admin_hold'])) {
                throw new UserFacingException('Order is not on hold.');
            }
            if (in_array($oldStatus, ['completed', 'rejected', 'cancelled'], true)) {
                throw new UserFacingException(sprintf(t($copy, 'cannot_cancel_status'), $oldStatus));
            }
            $restore = !empty($input['restore_stock']);
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
            throw new UserFacingException(sprintf(t($copy, 'unknown_action'), $action));
    }

    return ['status' => $newStatus, 'reason' => $logReason, 'old' => $oldStatus];
}
