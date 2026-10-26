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
if ($role !== 'farmer' && $role !== 'admin') {
    redirect_to('dashboard.php');
}

$pdo = DB::pdo();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$status = trim((string) ($_GET['status'] ?? ''));
// All nine statuses, matching orders.php — a "pending" filter on the report
// used to silently exclude in-flight orders.
$allowed = array_merge([''], ['pending', 'delivery_fee_review', 'confirmed', 'preparing',
    'ready_for_pickup', 'out_for_delivery', 'completed', 'rejected', 'cancelled']);
if (!in_array($status, $allowed, true)) $status = '';

$where = 'WHERE farmer_id = :id';
$params = ['id' => $userId];
if ($status !== '') { $where .= ' AND status = :status'; $params['status'] = $status; }

$oc = $pdo->prepare("SELECT COUNT(*) FROM v_order_summary $where");
$oc->execute($params);
$total = (int) $oc->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = max(1, min($pages, $page));
$os = $pdo->prepare("SELECT id, order_number, status, unit_price, quantity, delivery_fee, created_at FROM v_order_summary $where ORDER BY created_at DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) $os->bindValue(':' . $k, $v);
$os->bindValue(':lim', $perPage, PDO::PARAM_INT);
$os->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$os->execute();
$orders = $os->fetchAll();

// Earnings summary
$earnStmt = $pdo->prepare("SELECT COALESCE(SUM(unit_price * quantity + delivery_fee),0) AS total FROM v_order_summary WHERE farmer_id = :id AND status NOT IN ('cancelled','rejected')");
$earnStmt->execute(['id' => $userId]);
$totalEarnings = (float) $earnStmt->fetchColumn();
$completedCount = (int) $pdo->query("SELECT COUNT(*) FROM v_order_summary WHERE farmer_id = $userId AND status='completed'")->fetchColumn();
$cancelledCount = (int) $pdo->query("SELECT COUNT(*) FROM v_order_summary WHERE farmer_id = $userId AND status='cancelled'")->fetchColumn();

const REPORT_EXPORT_MAX_ROWS = 2000;

// CSV export: the full filtered set (page-size LIMIT is for display only),
// same pattern admin_reports.php uses with a sane cap.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportStmt = $pdo->prepare("SELECT * FROM v_order_summary $where ORDER BY created_at DESC LIMIT :lim");
    foreach ($params as $k => $v) $exportStmt->bindValue(':' . $k, $v);
    $exportStmt->bindValue(':lim', REPORT_EXPORT_MAX_ROWS, PDO::PARAM_INT);
    $exportStmt->execute();
    $exportRows = $exportStmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="farmer-sales.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($exportRows)) {
        if (count($exportRows) >= REPORT_EXPORT_MAX_ROWS) {
            // Cap note lands in the stream only if the cap was reached.
            fputcsv($out, ['note', 'export capped at ' . REPORT_EXPORT_MAX_ROWS . ' rows']);
        }
        fputcsv($out, array_keys($exportRows[0]));
        foreach ($exportRows as $r) fputcsv($out, array_values($r));
    }
    fclose($out);
    exit;
}

$activePage = 'reports';
$pageTitle = 'Sales Report';
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1>Sales Report</h1>
            <p>Track your earnings and order history.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i> Back</a>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card stat-card--success">
                    <div class="stat-content">
                        <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
                        <div class="stat-body">
                            <div class="stat-value"><?= number_format($totalEarnings, 2) ?></div>
                            <div class="stat-label">Total Earnings</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card stat-card--info">
                    <div class="stat-content">
                        <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                        <div class="stat-body">
                            <div class="stat-value"><?= $completedCount ?></div>
                            <div class="stat-label">Completed Orders</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card stat-card--warning">
                    <div class="stat-content">
                        <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                        <div class="stat-body">
                            <div class="stat-value"><?= $cancelledCount ?></div>
                            <div class="stat-label">Cancelled Orders</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="get" action="farmer_reports.php" class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div>
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" style="max-width:200px;" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($allowed as $s): if ($s === '') continue; ?>
                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="farmer_reports.php?export=csv" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
        </form>

        <?php if (!$orders): ?>
            <div class="empty-state text-center">
                <i class="bi bi-receipt empty-state-icon" aria-hidden="true"></i>
                <h3 class="empty-state-title">No orders yet</h3>
                <p class="empty-state-description">Once buyers place orders, they will appear here.</p>
            </div>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($orders as $order): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <span class="fw-semibold"><?= e($order['order_number'] ?? ('#' . $order['id'])) ?></span>
                            <div class="small text-muted"><?= e($order['status'] ?? '') ?> &middot; <?= money($order['unit_price'] * $order['quantity'] + $order['delivery_fee']) ?></div>
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
