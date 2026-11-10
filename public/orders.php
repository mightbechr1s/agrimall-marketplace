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

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$allowedStatuses = ['pending','delivery_fee_review','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','cancelled','rejected'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$column = $role === 'farmer' ? 'farmer_id' : 'buyer_id';
$where = "WHERE $column = :id";
$params = ['id' => $userId];
if ($statusFilter !== '') {
    $where .= " AND status = :status";
    $params['status'] = $statusFilter;
}

$oc = $pdo->prepare("SELECT COUNT(*) FROM v_order_summary $where");
$oc->execute($params);
$total = (int) $oc->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = max(1, min($pages, $page));
$os = $pdo->prepare("SELECT id, order_number, status, created_at, listing_id, quantity, listing_name, unit_symbol, unit_price FROM v_order_summary $where ORDER BY created_at DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) {
    $os->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$os->bindValue(':lim', $perPage, PDO::PARAM_INT);
$os->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$os->execute();
$orders = $os->fetchAll();

$activePage = 'orders';
$pageTitle = 'My Orders';
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1>My Orders</h1>
            <p>Track your orders and purchase history.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to dashboard</a>

        <form method="get" action="orders.php" class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div>
                <label class="form-label small mb-1" for="status-filter">Status</label>
                <select id="status-filter" name="status" class="form-select form-select-sm" style="max-width:200px;" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="orders.php" class="btn btn-sm btn-outline-primary">Reset</a>
        </form>

        <?php
        $statusBadge = static function (string $s): string {
            return match ($s) {
                'completed' => 'text-bg-success',
                'cancelled', 'rejected' => 'text-bg-danger',
                'pending', 'delivery_fee_review' => 'text-bg-warning',
                default => 'text-bg-secondary',
            };
        };
        ?>
        <?php if (!$orders): ?>
            <div class="empty-state text-center">
                <i class="bi bi-receipt empty-state-icon" aria-hidden="true"></i>
                <h3 class="empty-state-title">No orders found</h3>
                <p class="empty-state-description">No orders match the selected filter.</p>
            </div>
        <?php else: ?>
            <div class="card">
                <ul class="list-group list-group-flush">
                    <?php foreach ($orders as $order): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <span class="fw-semibold"><?= e($order['order_number'] ?? ('#' . $order['id'])) ?></span>
                                <div class="small mt-1"><span class="badge <?= $statusBadge($order['status'] ?? '') ?>"><?= e(ucfirst(str_replace('_',' ',$order['status'] ?? ''))) ?></span></div>
                                <?php if ($role === 'buyer' && $order['status'] === 'completed' && !empty($order['listing_id'])): ?>
                                <form method="post" action="cart_add.php" class="mt-2 mb-0" data-buy-now="true">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="listing_id" value="<?= (int) $order['listing_id'] ?>">
                                    <input type="hidden" name="quantity" value="<?= (float) $order['quantity'] ?>">
                                    <input type="hidden" name="buy_now" value="1">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Buy again</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <div class="text-nowrap small text-muted">
                                <?= e(date('M j, g:i A', strtotime((string) ($order['created_at'] ?? 'now')))) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
