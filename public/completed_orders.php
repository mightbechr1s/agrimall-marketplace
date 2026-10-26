<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function ($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

require_login();
$userId = current_user_id();
$role = current_user_role();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$pdo = DB::pdo();

if ($role === 'farmer') {
    $where = 'WHERE farmer_id = :id AND status = \'completed\'';
} else {
    $where = 'WHERE buyer_id = :id AND status = \'completed\'';
}
$oc = $pdo->prepare("SELECT COUNT(*) FROM v_order_summary $where");
$oc->execute(['id' => $userId]);
$total = (int) $oc->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = max(1, min($pages, $page));
$os = $pdo->prepare("SELECT id, order_number, status, created_at FROM v_order_summary $where ORDER BY created_at DESC LIMIT :lim OFFSET :off");
$os->bindValue(':id', $userId, PDO::PARAM_INT);
$os->bindValue(':lim', $perPage, PDO::PARAM_INT);
$os->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$os->execute();
$orders = $os->fetchAll();

$activePage = 'completed';
$pageTitle = 'Completed Orders';
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1>Completed Orders Archive</h1>
            <p>Your completed orders, archived for reference.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to dashboard</a>

        <?php if (!$orders): ?>
            <div class="empty-state text-center">
                <i class="bi bi-archive empty-state-icon" aria-hidden="true"></i>
                <h3 class="empty-state-title">No completed orders yet</h3>
                <p class="empty-state-description">Completed orders will appear here once fulfilled.</p>
            </div>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($orders as $order): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <span class="fw-semibold"><?= e($order['order_number'] ?? ('#' . $order['id'])) ?></span>
                            <div class="small text-muted"><?= e($order['status'] ?? '') ?></div>
                        </div>
                        <div class="text-nowrap small text-muted">
                            <?= e(date('M j, g:i A', strtotime((string) ($order['created_at'] ?? 'now')))) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
