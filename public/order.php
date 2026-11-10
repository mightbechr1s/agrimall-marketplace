<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$i18nAll = include __DIR__ . '/../src/i18n_copy.php';
$ordersCopy = $i18nAll['orders'] ?? [];

$orderId = (int) ($_GET['id'] ?? 0);
if (!$orderId) {
    flash_set('error', t($ordersCopy, 'invalid_order'));
    redirect_to('/dashboard.php');
}

$pdo = DB::pdo();
$userId = current_user_id();
$role = current_user_role();

// Load order with access check
$stmt = $pdo->prepare('SELECT * FROM v_order_summary WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();
if (!$order) {
    flash_set('error', t($ordersCopy, 'order_not_found'));
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

// A completed order may carry one review from the buyer.
$reviewStmt = $pdo->prepare('SELECT rating, comment, status, created_at FROM farmer_reviews WHERE order_id = :id');
$reviewStmt->execute(['id' => $orderId]);
$review = $reviewStmt->fetch() ?: null;
$canReview = $role === 'buyer' && $order['status'] === 'completed' && !$review;

// Order thread. Reading the thread is what marks the other side's messages read;
// an admin looking at the order does not clear anyone's unread badge.
$msgStmt = $pdo->prepare(
    'SELECT id, sender_id, sender_role, body, read_at, created_at
       FROM order_messages
      WHERE order_id = :id
      ORDER BY created_at ASC'
);
$msgStmt->execute(['id' => $orderId]);
$messages = $msgStmt->fetchAll();
$canMessage = in_array($role, ['buyer', 'farmer'], true);
if ($canMessage) {
    $pdo->prepare(
        'UPDATE order_messages SET read_at = NOW()
          WHERE order_id = :id AND sender_id <> :me AND read_at IS NULL'
    )->execute(['id' => $orderId, 'me' => $userId]);
}

$pageTitle = 'Order ' . $order['order_number'];
$activePage = 'orders';
require __DIR__ . '/partials/dashboard-layout.php';
?>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-1"><?= e($order['order_number']) ?></h1>
            <?= status_badge($order['status']) ?>
            <?php if ($role === 'buyer'): ?>
            <a href="order_track.php?id=<?= $orderId ?>" class="btn btn-outline-primary btn-sm ms-2"><i class="bi bi-geo-alt me-1"></i>Track</a>
            <?php endif; ?>
        </div>
        <a href="/dashboard.php" class="btn btn-outline btn-sm">Back to Dashboard</a>
    </div>

    <?php if ($order['status'] === 'delivery_fee_review'): ?>
        <div class="alert alert-warning" role="status">
            <i class="bi bi-truck me-1"></i>
            <?php if ($role === 'farmer'): ?>
                <strong>Quote needed:</strong> enter the delivery fee below and click "Set Fee" to send the quote to the buyer.
            <?php else: ?>
                <strong>Awaiting delivery fee quote:</strong> the farmer is preparing a delivery fee. You'll be notified once it's set.
            <?php endif; ?>
        </div>
    <?php endif; ?>

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
                        <?php if ($role === 'buyer'): ?>
                        <form method="post" action="cart_add.php" class="mt-2 mb-0" data-buy-now="true">
                            <?= csrf_field() ?>
                            <input type="hidden" name="listing_id" value="<?= (int) $order['listing_id'] ?>">
                            <input type="hidden" name="quantity" value="<?= (float) $order['quantity'] ?>">
                            <input type="hidden" name="buy_now" value="1">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Buy again</button>
                        </form>
                        <?php endif; ?>
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
                    <?php
                      $confirmed = in_array($order['status'], ['confirmed','preparing','ready_for_pickup','out_for_delivery','completed'], true);
                    ?>
                    <div class="participant-card">
                        <strong>Buyer</strong>
                        <span class="participant-name"><?= e($order['buyer_name']) ?></span>
                        <?php if ($confirmed): ?>
                          <small class="text-muted"><?= e($order['buyer_phone'] ?? 'No phone') ?></small>
                        <?php else: ?>
                          <small class="text-muted fst-italic">Contact details shared after confirmation.</small>
                        <?php endif; ?>
                    </div>
                    <div class="participant-card">
                        <strong>Farm</strong>
                        <span class="participant-name"><?= e($order['farm_name']) ?></span>
                        <?php if ($confirmed): ?>
                          <small class="text-muted"><?= e($order['farmer_contact_person'] ?? '') ?> - <?= e($order['farmer_phone'] ?? 'No phone') ?></small>
                        <?php else: ?>
                          <small class="text-muted fst-italic">Contact details shared after confirmation.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div class="dash-panel p-4 mt-4" id="messages">
                <h5 class="mb-3">Messages</h5>
                <div class="message-thread mb-3">
                    <?php if (!$messages): ?>
                        <p class="text-muted small mb-0">No messages yet. Use this thread to agree on quantity, pickup, or delivery.</p>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <?php $mine = (int) $m['sender_id'] === $userId; ?>
                            <div class="message <?= $mine ? 'message--mine' : '' ?>">
                                <div class="message-meta">
                                    <?= $mine ? 'You' : e($m['sender_role'] === 'farmer' ? $order['farm_name'] : $order['buyer_name']) ?>
                                    · <?= e(date('M j, g:i A', strtotime($m['created_at']))) ?>
                                </div>
                                <div><?= nl2br(e($m['body'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($canMessage): ?>
                    <form method="post" action="/message_send.php" class="d-flex gap-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <div class="flex-grow-1">
                            <label class="visually-hidden" for="message-body">Message</label>
                            <textarea id="message-body" name="body" class="form-control form-control-sm" rows="2" maxlength="2000" required placeholder="Write a message…"></textarea>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">Send</button>
                    </form>
                <?php endif; ?>
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
                                        <?= status_badge($h['to_status']) ?>
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

            <!-- Review -->
            <?php if ($review || $canReview): ?>
            <div class="dash-panel p-4 mt-4" id="review">
                <h5 class="mb-3"><?= $role === 'buyer' ? 'Your review' : 'Buyer review' ?></h5>
                <?php if ($review): ?>
                    <div class="mb-2" aria-label="<?= (int) $review['rating'] ?> out of 5 stars">
                        <span class="text-warning" aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= (int) $review['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i><?php endfor; ?></span>
                        <span class="text-muted small ms-2"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                        <?php if ($review['status'] === 'hidden'): ?>
                            <span class="badge text-bg-secondary ms-1">Hidden by admin</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($review['comment']): ?>
                        <p class="mb-0"><?= nl2br(e($review['comment'])) ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">No comment.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" action="/review_submit.php" class="action-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <div class="mb-2">
                            <label class="form-label small" for="rating">Rating</label>
                            <select class="form-select form-select-sm" id="rating" name="rating" required>
                                <option value="">Choose…</option>
                                <option value="5">5 — Excellent</option>
                                <option value="4">4 — Good</option>
                                <option value="3">3 — Okay</option>
                                <option value="2">2 — Poor</option>
                                <option value="1">1 — Bad</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="comment">Comment (optional)</label>
                            <textarea class="form-control form-control-sm" id="comment" name="comment" rows="3" maxlength="1000" placeholder="How was the produce and the handover?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Post review</button>
                        <p class="form-text small mb-0 mt-2">Reviews cannot be edited after posting.</p>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
                                    <div class="form-text small">Enter 0 for free delivery.</div>
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
                                <div class="form-text small">Enter 0 for free delivery.</div>
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
                        <!-- Pickup: buyer completes, not farmer -->
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
                        <!-- Delivery: farmer completes -->
                    <?php endif; ?>

                <?php elseif (in_array($role, ['admin', 'manager'], true)): ?>
                    <?php if (!empty($order['admin_hold'])): ?>
                        <div class="alert alert-warning small mb-2">
                            <strong>Held by administrator.</strong>
                            <?php if (!empty($order['admin_hold_reason'])): ?>
                                <div class="mt-1"><?= e($order['admin_hold_reason']) ?></div>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="release_hold">
                            <button type="submit" class="btn btn-outline-primary w-100 btn-sm">Release hold</button>
                        </form>
                        <form method="post" action="/order_action.php" class="action-form w-100 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="cancel_held">
                            <div class="mb-2">
                                <label class="form-label small">Reason (required)</label>
                                <textarea name="reason" class="form-control form-control-sm" rows="2" maxlength="<?= FIELD_LIMITS['reason'] ?>" required></textarea>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="restore_stock" value="1" id="restoreStock">
                                <label class="form-check-label small" for="restoreStock">Restore stock</label>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm">Cancel held order</button>
                        </form>
                    <?php endif; ?>
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

<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>