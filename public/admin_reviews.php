<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin', 'manager');

// Moderation, not deletion: a hidden review stays in the table so the decision
// remains auditable, but it drops out of the public average and profile list.
$pdo = DB::pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $hide = ($_POST['action'] ?? '') === 'hide';
    $reason = trim((string) ($_POST['reason'] ?? ''));

    $stmt = $pdo->prepare('SELECT id FROM farmer_reviews WHERE id = :id');
    $stmt->execute(['id' => $reviewId]);
    if (!$stmt->fetch()) {
        flash_set('error', 'Review not found.');
        redirect_to('admin_reviews.php');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE farmer_reviews SET status = :status, hidden_reason = :reason WHERE id = :id')
            ->execute([
                'status' => $hide ? 'hidden' : 'visible',
                'reason' => $hide && $reason !== '' ? mb_substr($reason, 0, 255) : null,
                'id'     => $reviewId,
            ]);
        $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, reason, created_at)
             VALUES (:admin, :action, :type, :id, :reason, NOW())'
        )->execute([
            'admin'  => current_user_id(),
            'action' => $hide ? 'review_hidden' : 'review_unhidden',
            'type'   => 'farmer_review',
            'id'     => $reviewId,
            'reason' => $hide && $reason !== '' ? mb_substr($reason, 0, 255) : null,
        ]);
        $pdo->commit();
        flash_set('success', $hide ? 'Review hidden.' : 'Review restored.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash_set('error', generic_error_message(app_log_exception($e, 'admin_reviews')));
    }
    redirect_to('admin_reviews.php');
}

$reviews = $pdo->query(
    "SELECT r.id, r.rating, r.comment, r.status, r.hidden_reason, r.created_at,
            r.order_id, u.email AS buyer_email,
            COALESCE(bp.full_name, u.email) AS buyer_name,
            fp.farm_name
       FROM farmer_reviews r
       JOIN users u ON u.id = r.buyer_id
       LEFT JOIN buyer_profiles bp ON bp.user_id = r.buyer_id
       LEFT JOIN farmer_profiles fp ON fp.user_id = r.farmer_id
      ORDER BY r.created_at DESC
      LIMIT 100"
)->fetchAll();

$pageTitle = 'Reviews';
$activePage = 'reviews';
$role = 'admin';
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="dash-section-header">
    <h1>Reviews</h1>
    <p>Hide abusive or fake reviews. Hidden reviews stay on record but leave the public average.</p>
</div>

<?php if ($flash = flash_get('success')): ?>
    <div class="alert alert-success" role="alert"><?= e($flash) ?></div>
<?php endif; ?>
<?php if ($flash = flash_get('error')): ?>
    <div class="alert alert-danger" role="alert"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (!$reviews): ?>
    <div class="dash-panel p-4 text-muted">No reviews yet.</div>
<?php else: ?>
    <div class="dash-panel">
        <div class="table-responsive">
            <table class="dash-table align-middle">
                <thead>
                    <tr>
                        <th>Farm</th>
                        <th>Buyer</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td data-label="Farm"><?= e($r['farm_name'] ?? '—') ?></td>
                            <td data-label="Buyer"><?= e($r['buyer_name']) ?></td>
                            <td data-label="Rating">
                                <span class="text-warning" aria-label="<?= (int) $r['rating'] ?> out of 5">
                                    <span aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= (int) $r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i><?php endfor; ?></span>
                                </span>
                            </td>
                            <td data-label="Review">
                                <?= $r['comment'] !== null && $r['comment'] !== '' ? nl2br(e($r['comment'])) : '<span class="text-muted">No comment</span>' ?>
                                <div class="text-muted small">
                                    <?= e(date('M j, Y', strtotime($r['created_at']))) ?>
                                    · <a href="order.php?id=<?= (int) $r['order_id'] ?>">Order #<?= (int) $r['order_id'] ?></a>
                                </div>
                            </td>
                            <td data-label="Status">
                                <?php if ($r['status'] === 'visible'): ?>
                                    <span class="badge text-bg-success">Visible</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Hidden</span>
                                    <?php if ($r['hidden_reason']): ?>
                                        <div class="text-muted small"><?= e($r['hidden_reason']) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="Action" class="text-end">
                                <form method="post" action="admin_reviews.php" class="d-inline-flex gap-1 align-items-center justify-content-end flex-wrap">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                                    <?php if ($r['status'] === 'visible'): ?>
                                        <input type="hidden" name="action" value="hide">
                                        <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 160px;" maxlength="255" placeholder="Reason (optional)" aria-label="Reason for hiding">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hide</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="restore">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Restore</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
