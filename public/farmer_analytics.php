<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$navCopy = $copy;

require_login();
$role = current_user_role();
$userId = current_user_id();
if ($role !== 'farmer') {
    redirect_to('dashboard.php');
}

$pageTitle = 'Analytics';
$activePage = 'analytics';

$pdo = DB::pdo();

// Listing summary (real data)
$listingSummaryStmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(available_quantity), 0) AS qty
     FROM produce_listings WHERE farmer_id = :id GROUP BY status'
);
$listingSummaryStmt->execute(['id' => $userId]);
$listingSummary = $listingSummaryStmt->fetchAll();

$listingsTotalStmt = $pdo->prepare('SELECT COUNT(*) FROM produce_listings WHERE farmer_id = :id AND status <> \'archived\'');
$listingsTotalStmt->execute(['id' => $userId]);
$listingsTotal = (int) $listingsTotalStmt->fetchColumn();

$listingsTotalQtyStmt = $pdo->prepare('SELECT COALESCE(SUM(available_quantity), 0) FROM produce_listings WHERE farmer_id = :id AND status <> \'archived\'');
$listingsTotalQtyStmt->execute(['id' => $userId]);
$listingsTotalQty = (float) $listingsTotalQtyStmt->fetchColumn();

// Order summary (real data)
$orderSummaryStmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS cnt,
            COALESCE(SUM(unit_price * quantity), 0) AS produce,
            COALESCE(SUM(delivery_fee), 0) AS fees
     FROM v_order_summary WHERE farmer_id = :id GROUP BY status'
);
$orderSummaryStmt->execute(['id' => $userId]);
$orderSummary = $orderSummaryStmt->fetchAll();
$orderSummaryByStatus = [];
foreach ($orderSummary as $row) {
    $orderSummaryByStatus[$row['status']] = $row;
}

$oc = $pdo->prepare('SELECT COUNT(*) FROM v_order_summary WHERE farmer_id = :id');
$oc->execute(['id' => $userId]);
$orderTotal = (int) $oc->fetchColumn();

$listingFlowOrder = ['active', 'paused', 'draft', 'sold_out', 'archived'];
$orderFlowOrder = ['pending', 'delivery_fee_review', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'rejected', 'cancelled'];
$listingMap = [];
foreach ($listingSummary as $row) {
    $listingMap[$row['status']] = $row;
}
$listingOrdered = array_values(array_filter(array_map(
    function ($s) use ($listingMap) { return $listingMap[$s] ?? null; },
    $listingFlowOrder
)));
$orderOrdered = array_values(array_filter(array_map(
    function ($s) use ($orderSummaryByStatus) { return $orderSummaryByStatus[$s] ?? null; },
    $orderFlowOrder
)));

require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="dash-section-header">
    <h1>Analytics</h1>
    <p>Full breakdown of your listings and orders. Generated <?= e(date('M j, Y \a\t g:i A')) ?>.</p>
    <a href="dashboard.php" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
</div>

<div class="dash-panel" id="analytics">
    <div class="dash-panel-header">
        <h2><i class="bi bi-graph-up me-2"></i>Analytics</h2>
        <span class="dash-panel-count">Live data</span>
    </div>
    <div class="dash-panel-body">
        <div class="row g-4">
            <div class="col-lg-6">
                <h3 class="fs-6 fw-semibold mb-2">Listing Quantities</h3>
                <p class="text-muted small mb-2">Quantities per status; totals mix unit types (e.g. bundle, kg).</p>
                <?php if ($listingOrdered): ?>
                <div class="table-responsive">
                    <table class="dash-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Listings</th>
                                <th class="text-end">Available Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listingOrdered as $row): ?>
                                <tr>
                                    <td><?= status_badge($row['status']) ?></td>
                                    <td class="text-end"><?= (int) $row['cnt'] ?></td>
                                    <td class="text-end"><?= rtrim(rtrim(number_format((float) $row['qty'], 2), '0'), '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-end"><?= $listingsTotal ?></th>
                                <th class="text-end"><?= rtrim(rtrim(number_format($listingsTotalQty, 2), '0'), '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No listings yet.</p>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <h3 class="fs-6 fw-semibold mb-2">Orders by Status</h3>
                <?php if ($orderOrdered): ?>
                <div class="table-responsive">
                    <table class="dash-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Orders</th>
                                <th class="text-end">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderOrdered as $row): ?>
                                <tr>
                                    <td><?= status_badge($row['status']) ?></td>
                                    <td class="text-end"><?= (int) $row['cnt'] ?></td>
                                    <td class="text-end"><?= money((float) $row['produce'] + (float) $row['fees']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-end"><?= $orderTotal ?></th>
                                <th class="text-end"><?= money(array_sum(array_map(function ($r) { return (float) $r['produce'] + (float) $r['fees']; }, $orderSummary))) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No orders yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>