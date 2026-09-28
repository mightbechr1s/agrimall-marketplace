<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$orderId = (int) ($_GET['id'] ?? 0);
if (!$orderId) {
    flash_set('error', 'Invalid order.');
    redirect_to('/dashboard.php');
}

$pdo = DB::pdo();
$userId = current_user_id();
$role = current_user_role();

$map = [
    'pending' => 'primary',
    'delivery_fee_review' => 'primary',
    'confirmed' => 'primary',
    'preparing' => 'primary',
    'ready_for_pickup' => 'primary',
    'out_for_delivery' => 'primary',
    'completed' => 'success',
    'rejected' => 'danger',
    'cancelled' => 'danger',
];

// Load order with access check
$stmt = $pdo->prepare('SELECT * FROM v_order_summary WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();
if (!$order) {
    flash_set('error', 'Order not found.');
    redirect_to('/dashboard.php');
}

// Role-based access control
if ($role === 'buyer' && (int) $order['buyer_id'] !== $userId) {
    http_response_code(403);
    exit('Forbidden.');
}
if ($role === 'farmer' && (int) $order['farmer_id'] !== $userId) {
    http_response_code(403);
    exit('Forbidden.');
}

// Load status history
$histStmt = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = :id ORDER BY created_at ASC');
$histStmt->execute(['id' => $orderId]);
$history = $histStmt->fetchAll();

$pageTitle = 'Order ' . e($order['order_number']);
require __DIR__ . '/partials/header.php';
?>

<main id="main-content" class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-1"><?= e($order['order_number']) ?></h1>
            <span class="dash-badge dash-badge--<?= e($map[$order['status']] ?? 'secondary') ?>">
                <?= e(str_replace('_', ' ', $order['status'])) ?>
            </span>
        </div>
        <a href="/dashboard.php" class="btn btn-outline">Back to Dashboard</a>
    </div>

    <?php if ($flash = flash_get('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash = flash_get('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= e($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Order Summary -->
        <div class="col-lg-8">
            <div class="dash-panel p-4">
                <h5 class="mb-3">Order Summary</h5>

                <div class="detail-row">
                    <span class="detail-label">Product</span>
                    <span class="detail-value">
                        <strong><?= e($order['listing_name']) ?></strong>
                        <br><small class="text-muted"><?= e($order['farm_name']) ?></small>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Quantity</span>
                    <span class="detail-value"><?= (float) $order['quantity'] ?> <?= e($order['unit_symbol']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Unit Price</span>
                    <span class="detail-value"><?= money($order['unit_price']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Delivery Fee</span>
                    <span class="detail-value"><?= money($order['delivery_fee']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total</span>
                    <span class="detail-value"><strong><?= money((float) $order['unit_price'] * (float) $order['quantity'] + (float) $order['delivery_fee']) ?></strong></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Delivery Method</span>
                    <span class="detail-value"><?= $order['delivery_method'] === 'farmer_delivery' ? 'Farmer Delivery' : 'Pickup' ?></span>
                </div>
                <?php if ($order['delivery_method'] === 'farmer_delivery' && $order['delivery_address']): ?>
                <div class="detail-row">
                    <span class="detail-label">Delivery Address</span>
                    <span class="detail-value"><?= e($order['delivery_address']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['delivery_method'] === 'pickup' && ($order['preferred_pickup_date'] || $order['preferred_pickup_time'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Preferred Pickup</span>
                    <span class="detail-value">
                        <?= $order['preferred_pickup_date'] ? date('M j, Y', strtotime($order['preferred_pickup_date'])) : 'Any date' ?>
                        <?= $order['preferred_pickup_time'] ? ' at ' . date('g:i A', strtotime($order['preferred_pickup_time'])) : '' ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($order['buyer_note']): ?>
                <div class="detail-row">
                    <span class="detail-label">Buyer Note</span>
                    <span class="detail-value"><?= e($order['buyer_note']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['farmer_note']): ?>
                <div class="detail-row">
                    <span class="detail-label">Farmer Note</span>
                    <span class="detail-value"><?= e($order['farmer_note']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['admin_note']): ?>
                <div class="detail-row">
                    <span class="detail-label">Admin Note</span>
                    <span class="detail-value"><?= e($order['admin_note']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Participants -->
            <div class="dash-panel p-4 mt-4">
                <h5 class="mb-3">Participants</h5>
                <div class="participants-grid">
                    <div class="participant-card">
                        <strong>Buyer</strong>
                        <span class="participant-name"><?= e($order['buyer_name']) ?></span>
                        <small class="text-muted"><?= e($order['buyer_phone'] ?? 'No phone') ?></small>
                    </div>
                    <div class="participant-card">
                        <strong>Farm</strong>
                        <span class="participant-name"><?= e($order['farm_name']) ?></span>
                        <small class="text-muted"><?= e($order['farmer_contact_person'] ?? '') ?> - <?= e($order['farmer_phone'] ?? 'No phone') ?></small>
                    </div>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="dash-panel p-4 mt-4">
                <h5 class="mb-3">Status Timeline</h5>
                <div class="order-timeline">
                    <?php foreach ($history as $idx => $h): ?>
                        <?php $isCurrent = ($idx === count($history) - 1); ?>
                        <div class="timeline-item <?= $isCurrent ? 'current' : 'completed' ?>">
                            <div class="d-flex gap-3">
                                <div class="text-muted small" style="min-width: 130px;">
                                    <?= date('M j, g:i A', strtotime($h['created_at'])) ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="dash-badge dash-badge--<?= in_array($h['to_status'], ['completed']) ? 'success' : (in_array($h['to_status'], ['rejected','cancelled']) ? 'danger' : 'primary') ?>">
                                            <?= e(str_replace('_', ' ', $h['to_status'])) ?>
                                        </span>
                                        <span class="text-muted small">by <?= e($h['changed_by_role']) ?></span>
                                    </div>
                                    <?php if ($h['reason']): ?>
                                        <div class="text-muted small"><?= e($h['reason']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($history)): ?>
                        <div class="text-center text-muted py-4">No status history yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Actions Sidebar -->
        <div class="col-lg-4">
            <div class="dash-panel p-4 sticky-top" style="top: 5rem;">
                <h5 class="mb-3">Actions</h5>

                <?php if ($role === 'farmer'): ?>
                    <?php if (in_array($order['status'], ['pending', 'delivery_fee_review'])): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="confirm">
                            <?php if ($order['delivery_method'] === 'farmer_delivery'): ?>
                                <div class="mb-2">
                                    <label class="form-label small">Delivery Fee (₱)</label>
                                    <input type="number" step="0.01" min="0" max="<?= MAX_DELIVERY_FEE ?>" name="delivery_fee" class="form-control form-control-sm" value="0.00" required>
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary w-100 btn-sm">
                                Confirm Order
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'delivery_fee_review'): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="set_fee">
                            <div class="mb-2">
                                <label class="form-label small">Delivery Fee (₱)</label>
                                <input type="number" step="0.01" min="0" max="<?= MAX_DELIVERY_FEE ?>" name="delivery_fee" class="form-control form-control-sm" value="<?= (float) $order['delivery_fee'] ?>" required>
                            </div>
                            <button type="submit" class="btn btn-outline w-100 btn-sm">
                                Set Fee
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (in_array($order['status'], ['pending', 'delivery_fee_review'])): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <div class="mb-2">
                                <label class="form-label small">Reason (required)</label>
                                <textarea name="reason" class="form-control form-control-sm" rows="2" maxlength="<?= FIELD_LIMITS['reason'] ?>" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                                Reject Order
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'confirmed'): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="prepare">
                            <button type="submit" class="btn btn-primary w-100 btn-sm">
                                Start Preparing
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'preparing'): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="ready">
                            <button type="submit" class="btn btn-primary w-100 btn-sm">
                                Mark Ready
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'ready_for_pickup'): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="ship">
                            <button type="submit" class="btn btn-primary w-100 btn-sm">
                                Mark Out for Delivery
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['delivery_method'] === 'farmer_delivery' && $order['status'] === 'out_for_delivery'): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn btn-success w-100 btn-sm">
                                Mark Delivered
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['delivery_method'] === 'pickup' && $order['status'] === 'ready_for_pickup'): ?>
                        <!-- Buyer completes pickup orders, not farmer -->
                    <?php endif; ?>

                    <?php if (in_array($order['status'], ['confirmed', 'preparing'])): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="cancel">
                            <div class="mb-2">
                                <label class="form-label small">Reason (required)</label>
                                <textarea name="reason" class="form-control form-control-sm" rows="2" maxlength="<?= FIELD_LIMITS['reason'] ?>" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                                Cancel Order
                            </button>
                        </form>
                    <?php endif; ?>

                <?php elseif ($role === 'buyer'): ?>
                    <?php if (in_array($order['status'], ['pending', 'delivery_fee_review', 'confirmed', 'preparing'])): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="cancel">
                            <div class="mb-2">
                                <label class="form-label small">Reason (required)</label>
                                <textarea name="reason" class="form-control form-control-sm" rows="2" maxlength="<?= FIELD_LIMITS['reason'] ?>" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                                Cancel Order
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['delivery_method'] === 'pickup' && $order['status'] === 'ready_for_pickup'): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn btn-success w-100 btn-sm">
                                Mark Picked Up (Complete)
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['delivery_method'] === 'farmer_delivery' && $order['status'] === 'out_for_delivery'): ?>
                        <!-- Farmer completes delivery orders -->
                    <?php endif; ?>

                <?php elseif ($role === 'admin'): ?>
                    <?php if (!in_array($order['status'], ['completed', 'rejected', 'cancelled'])): ?>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="cancel">
                            <div class="mb-2">
                                <label class="form-label small">Reason (required)</label>
                                <textarea name="reason" class="form-control form-control-sm" rows="2" maxlength="<?= FIELD_LIMITS['reason'] ?>" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                                Admin Cancel
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!in_array($order['status'], ['pending', 'delivery_fee_review', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])): ?>
                    <div class="text-center text-muted py-2">
                        No actions available for this status.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>