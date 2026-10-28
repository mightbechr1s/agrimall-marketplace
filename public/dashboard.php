<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$role = current_user_role();
$userId = current_user_id();
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Helpers
function order_total(array $order): string
{
    $total = ((float) $order['unit_price'] * (float) $order['quantity']) + (float) $order['delivery_fee'];
    return money($total);
}

$fmtQty = function ($value, $symbol): string {
    return rtrim(rtrim(number_format((float) $value, 4), '0'), '.') . ' ' . e($symbol);
};

// Data per role
$stats = [];
$orders = [];
$farmerListings = [];
$recentActivity = [];

if ($role === 'admin') {
    $pdo = DB::pdo();
    $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
    // Bound rather than concatenated. The value is server-derived today, but a
    // parameter is what keeps this safe if that ever changes.
    $countSince = function (string $table) use ($pdo, $weekAgo): int {
        // $table is a literal from this file, never user input.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE created_at >= :since");
        $stmt->execute(['since' => $weekAgo]);
        return (int) $stmt->fetchColumn();
    };
    $usersNew    = $countSince('users');
    $listingsNew = $countSince('produce_listings');
    $ordersNew   = $countSince('orders');

    $stats = [
        'Users' => [
            'value' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'trend' => $usersNew . ' new this week',
            'icon' => 'bi-people',
            'variant' => 'info',
            'href' => 'admin_reports.php?t=registration'
        ],
        'Pending Verifications' => [
            'value' => (int) $pdo->query('SELECT COUNT(*) FROM farmer_profiles WHERE verification_status = \'pending\'')->fetchColumn(),
            'trend' => 'awaiting review',
            'icon' => 'bi-patch-check-fill',
            'variant' => 'warning',
            'href' => 'admin_verify.php'
        ],
        'Active Listings' => [
            'value' => (int) $pdo->query('SELECT COUNT(*) FROM produce_listings WHERE status = \'active\'')->fetchColumn(),
            'trend' => $listingsNew . ' new this week',
            'icon' => 'bi-basket3-fill',
            'variant' => 'success',
            'href' => 'admin_reports.php?t=listings'
        ],
        'Pending Orders' => [
            'value' => (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE status IN (\'pending\',\'delivery_fee_review\')')->fetchColumn(),
            'trend' => $ordersNew . ' placed this week',
            'icon' => 'bi-clock-history',
            'variant' => 'warning',
            'href' => 'admin_reports.php?t=orders'
        ],
        'Completed Orders' => [
            'value' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn(),
            'trend' => 'all time',
            'icon' => 'bi-check2-all',
            'variant' => 'success',
            'href' => 'admin_reports.php?t=orders'
        ],
    ];
    $orders = $pdo->query('SELECT id, order_number, buyer_name, farm_name, listing_name, unit_symbol,
                                  quantity, unit_price, delivery_fee, delivery_method, status, created_at
                             FROM v_order_summary ORDER BY created_at DESC LIMIT 10')->fetchAll();

    $pendingFarmers = $pdo->query('SELECT u.id AS user_id, u.created_at, fp.farm_name, fp.municipality_city, fp.province
                                   FROM farmer_profiles fp
                                   JOIN users u ON u.id = fp.user_id
                                   WHERE fp.verification_status = \'pending\'
                                   ORDER BY u.created_at ASC LIMIT 5')->fetchAll();
    $recentAudit = $pdo->query('SELECT al.action, al.entity_type, al.entity_id, al.created_at, u.email AS admin_email
                                FROM (SELECT * FROM admin_audit_log ORDER BY created_at DESC LIMIT 5) al
                                LEFT JOIN users u ON u.id = al.admin_id')->fetchAll();

    $expiredListings = 0;
    $heldOrders = 0;
    $suspendedUsers = 0;
    try { $expiredListings = (int) $pdo->query("SELECT COUNT(*) FROM produce_listings WHERE status = 'active' AND expiry_date IS NOT NULL AND expiry_date <> '' AND expiry_date < CURDATE()")->fetchColumn(); } catch (Throwable $e) { }
    try { $heldOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE admin_hold = 1")->fetchColumn(); } catch (Throwable $e) { }
    try { $suspendedUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn(); } catch (Throwable $e) { }

} elseif ($role === 'farmer') {
    $pdo = DB::pdo();
    $mine = $pdo->prepare('SELECT COUNT(*) FROM produce_listings WHERE farmer_id = :id');
    $mine->execute(['id' => $userId]);
    $activeCount = $pdo->prepare('SELECT COUNT(*) FROM produce_listings WHERE farmer_id = :id AND status = \'active\'');
    $activeCount->execute(['id' => $userId]);
    $pendingOrders = $pdo->prepare('SELECT COUNT(*) FROM v_order_summary WHERE farmer_id = :id AND status IN (\'pending\',\'confirmed\',\'preparing\')');
    $pendingOrders->execute(['id' => $userId]);

    // Total earnings and volume sold — straight off the view; the view already
    // carries unit_price/quantity/delivery_fee, so the old self-join against
    // `orders o` doubled every row lookup for nothing.
    $earningsStmt = $pdo->prepare('SELECT COALESCE(SUM(unit_price * quantity + delivery_fee), 0) FROM v_order_summary WHERE farmer_id = :id AND status NOT IN (\'cancelled\',\'rejected\')');
    $earningsStmt->execute(['id' => $userId]);
    $totalEarnings = money((float) $earningsStmt->fetchColumn());

    $volumeStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM v_order_summary WHERE farmer_id = :id AND status NOT IN (\'cancelled\',\'rejected\')');
    $volumeStmt->execute(['id' => $userId]);
    $totalVolume = rtrim(rtrim(number_format((float) $volumeStmt->fetchColumn(), 2), '0'), '.') . ' units';

    // Completed/closed counts for stat-card trends (real data, no full summary needed).
    $completedStmt = $pdo->prepare("SELECT COUNT(*) FROM v_order_summary WHERE farmer_id = :id AND status = 'completed'");
    $completedStmt->execute(['id' => $userId]);
    $completed = (int) $completedStmt->fetchColumn();
    $closedStmt = $pdo->prepare("SELECT COUNT(*) FROM v_order_summary WHERE farmer_id = :id AND status IN ('cancelled','rejected')");
    $closedStmt->execute(['id' => $userId]);
    $closed = (int) $closedStmt->fetchColumn();

    $stats = [
        'Total Earnings' => [
            'value' => $totalEarnings,
            'trend' => $completed . ' completed order' . ($completed === 1 ? '' : 's'),
            'icon' => 'bi-currency-dollar',
            'type' => 'money',
            'variant' => 'success',
            'href' => 'dashboard.php#orders'
        ],
        'Volume Sold' => [
            'value' => $totalVolume,
            'trend' => $closed . ' cancelled/rejected',
            'icon' => 'bi-box-seam',
            'variant' => 'info',
            'href' => 'dashboard.php#listings'
        ],
        'Pending Orders' => [
            'value' => (int) $pendingOrders->fetchColumn(),
            'trend' => 'awaiting action',
            'icon' => 'bi-clock-history',
            'variant' => 'warning',
            'href' => 'dashboard.php#orders'
        ],
    ];

    // Order history grows forever, so the table is paged and the totals come
    // from COUNT queries. Counting the fetched page would silently corrupt the
    // panel counts and the Total/Active/Completed stats below.
    $ordersPerPage = 15;
    $oc = $pdo->prepare('SELECT COUNT(*) FROM v_order_summary WHERE farmer_id = :id');
    $oc->execute(['id' => $userId]);
    $orderTotal = (int) $oc->fetchColumn();
    $orderPages = max(1, (int) ceil($orderTotal / $ordersPerPage));
    $orderPage = max(1, min($orderPages, (int) ($_GET['orders_page'] ?? 1)));
    $os = $pdo->prepare('SELECT * FROM v_order_summary WHERE farmer_id = :id ORDER BY created_at DESC LIMIT :lim OFFSET :off');
    $os->bindValue(':id', $userId, PDO::PARAM_INT);
    $os->bindValue(':lim', $ordersPerPage, PDO::PARAM_INT);
    $os->bindValue(':off', ($orderPage - 1) * $ordersPerPage, PDO::PARAM_INT);
    $os->execute();
    $orders = $os->fetchAll();

} else {
    // Buyer
    $pdo = DB::pdo();
    $ordersPerPage = 15;
    $oc = $pdo->prepare('SELECT COUNT(*) FROM v_order_summary WHERE buyer_id = :id');
    $oc->execute(['id' => $userId]);
    $orderTotal = (int) $oc->fetchColumn();
    $oca = $pdo->prepare("SELECT COUNT(*) FROM v_order_summary
                          WHERE buyer_id = :id AND status NOT IN ('completed','cancelled','rejected')");
    $oca->execute(['id' => $userId]);
    $activeOrders = (int) $oca->fetchColumn();
    $occ = $pdo->prepare("SELECT COUNT(*) FROM v_order_summary
                          WHERE buyer_id = :id AND status = 'completed'");
    $occ->execute(['id' => $userId]);
    $completedOrders = (int) $occ->fetchColumn();
    $orderPages = max(1, (int) ceil($orderTotal / $ordersPerPage));
    $orderPage = max(1, min($orderPages, (int) ($_GET['orders_page'] ?? 1)));
    $os = $pdo->prepare('SELECT * FROM v_order_summary WHERE buyer_id = :id ORDER BY created_at DESC LIMIT :lim OFFSET :off');
    $os->bindValue(':id', $userId, PDO::PARAM_INT);
    $os->bindValue(':lim', $ordersPerPage, PDO::PARAM_INT);
    $os->bindValue(':off', ($orderPage - 1) * $ordersPerPage, PDO::PARAM_INT);
    $os->execute();
    $orders = $os->fetchAll();
    $stats = [
        'Total Orders' => [
            'value' => $orderTotal,
            'trend' => $activeOrders . ' active',
            'icon' => 'bi-receipt',
            'variant' => 'info',
            'href' => 'orders.php'
        ],
        'Active Orders' => [
            'value' => $activeOrders,
            'trend' => 'in progress',
            'icon' => 'bi-clock',
            'variant' => 'warning',
            'href' => 'orders.php'
        ],
        'Completed' => [
            'value' => $completedOrders,
            'trend' => 'delivered',
            'icon' => 'bi-check-circle',
            'variant' => 'success',
            'href' => 'completed_orders.php'
        ],
    ];
}

require __DIR__ . '/partials/dashboard-layout.php';
?>

<?php if ($role === 'admin'): ?>
    <!-- Admin Dashboard -->
    <div class="dash-section-header">
        <h1>Platform Overview</h1>
        <p>Monitor AniMALL marketplace activity across all users and listings.</p>
    </div>

    <div class="row g-4 mb-4" data-reveal="stagger">
        <?php foreach ($stats as $label => $data): ?>
            <div class="col-12 col-sm-6 col-lg-3">
<div class="stat-card stat-card--<?= e($data['variant'] ?? 'info') ?>" data-reveal="stagger" data-href="<?= e($data['href'] ?? '#') ?>" onclick="if(this.dataset.href&&this.dataset.href!=='#')window.location.href=this.dataset.href;" style="cursor:pointer;">
                    <div class="stat-content">
                        <div class="stat-icon"><i class="bi <?= e($data['icon']) ?>" aria-hidden="true"></i></div>
                        <div class="stat-body">
                            <div class="stat-value" data-count data-stat-target="<?= is_int($data['value']) ? (int) $data['value'] : 0 ?>">
                                <?= e(is_string($data['value']) ? $data['value'] : (string) $data['value']) ?>
                            </div>
                            <div class="stat-label"><?= e($label) ?></div>
                            <div class="stat-trend text-success small"><?= e($data['trend']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Orders -->
    <div class="dash-panel" id="orders">
        <div class="dash-panel-header">
            <h2>Recent Orders</h2>
            <span class="dash-panel-count"><?= count($orders) ?> orders</span>
        </div>
        <?php if ($orders): ?>
        <div class="table-responsive">
            <table class="dash-table align-middle">
                <thead>
                    <tr>
                        <th class="text-nowrap">Order</th>
                        <th>Buyer</th>
                        <th>Farm</th>
                        <th>Item</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end date-col">Date</th>
                        <th class="text-end action-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        // Truncate order ID to 8-char hex tag
                        $orderId = strtoupper(substr(md5($order['id']), 0, 8));
                        ?>
                        <tr class="order-row clickable-row"
                           tabindex="0" role="button" aria-label="View order details for order #<?= $orderId ?>"
                           data-order-id="<?= $order['id'] ?>"
                           data-buyer-name="<?= e($order['buyer_name']) ?>"
                           data-farm-name="<?= e($order['farm_name']) ?>"
                           data-listing-name="<?= e($order['listing_name']) ?>"
                           data-unit-price="<?= e($order['unit_price']) ?>"
                           data-quantity="<?= e($order['quantity']) ?>"
                           data-delivery-fee="<?= e($order['delivery_fee']) ?>"
                           data-status="<?= e($order['status']) ?>"
                           data-created-at="<?= e($order['created_at']) ?>"
                           data-total-raw="<?= (float) $order['unit_price'] * (float) $order['quantity'] + (float) $order['delivery_fee'] ?>">
                            <td class="text-nowrap order-id-tag">#<?= $orderId ?></td>
                            <td><?= e($order['buyer_name']) ?></td>
                            <td><?= e($order['farm_name']) ?></td>
                            <td><?= e($order['listing_name']) ?></td>
                            <td class="text-end"><?= order_total($order) ?></td>
                            <td><?= status_badge($order['status']) ?></td>
                            <td class="text-end date-col text-muted"><?= e(date('M j, g:i A', strtotime($order['created_at']))) ?></td>
                            <td class="text-end action-col">
                                <a href="/order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon" aria-hidden="true">
            <path d="M5.757 1.071a.5.5 0 0 1 .172.686L3.383 6h9.234l1.12-4.243a.5.5 0 0 1 .172-.686zM.5 8a.5.5 0 0 1 .5.5V13a1.5 1.5 0 0 0 1.5 1.5h9a1.5 1.5 0 0 0 1.5-1.5V8.5a.5.5 0 0 1 1 0v4.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 0.5 12.5V8.5a.5.5 0 0 1 .5-.5z"/>
          </svg>
          <h2 class="empty-state-title">No orders on the platform yet</h2>
          <p class="empty-state-description">Start by managing listings or wait for buyers to place orders.</p>
          <a href="browse.php" class="btn btn-primary">Browse Marketplace</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Needs attention -->
    <?php if ($expiredListings > 0 || $heldOrders > 0 || $suspendedUsers > 0): ?>
    <div class="dash-panel mt-4">
        <div class="dash-panel-header">
            <h2><i class="bi bi-exclamation-triangle me-2"></i>Needs attention</h2>
        </div>
        <div class="dash-panel-body">
            <ul class="list-unstyled mb-0 small">
                <?php if ($expiredListings > 0): ?>
                    <li class="mb-1"><i class="bi bi-calendar-x me-1 text-warning" aria-hidden="true"></i><?= (int) $expiredListings ?> expired listing<?= $expiredListings === 1 ? '' : 's' ?> still active</li>
                <?php endif; ?>
                <?php if ($heldOrders > 0): ?>
                    <li class="mb-1"><i class="bi bi-pause-circle me-1 text-warning" aria-hidden="true"></i><?= (int) $heldOrders ?> held order<?= $heldOrders === 1 ? '' : 's' ?> awaiting resolution</li>
                <?php endif; ?>
                <?php if ($suspendedUsers > 0): ?>
                    <li class="mb-1"><i class="bi bi-slash-circle me-1 text-danger" aria-hidden="true"></i><?= (int) $suspendedUsers ?> suspended account<?= $suspendedUsers === 1 ? '' : 's' ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Farmer Verifications + Audit Activity -->
    <div class="row g-4 mt-4">
        <div class="col-lg-6">
            <div class="dash-panel">
                <div class="dash-panel-header">
                    <h2><i class="bi bi-patch-check-fill me-2"></i>Farmer Verifications</h2>
                    <a href="admin_verify.php?status=pending" class="btn btn-sm btn-primary">Review all</a>
                </div>
                <?php if ($pendingFarmers): ?>
                    <?php foreach ($pendingFarmers as $f): ?>
                        <div class="priority-queue-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <h5 class="mb-1"><?= e($f['farm_name']) ?></h5>
                                    <p class="text-muted mb-0 small">
                                        <?= e($f['municipality_city']) ?>, <?= e($f['province']) ?>
                                        &middot; Registered <?= e(date('M j', strtotime($f['created_at']))) ?>
                                    </p>
                                </div>
                                <span class="dash-badge dash-badge--warning">pending</span>
                            </div>
                            <form method="post" action="admin_verify.php" class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $f['user_id'] ?>">
                                <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 200px;"
                                       maxlength="<?= FIELD_LIMITS['reason'] ?>"
                                       placeholder="Reject reason" aria-label="Reject reason">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-primary">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state p-4">
                        <h2 class="empty-state-title h6">No pending verifications</h2>
                        <p class="empty-state-description small mb-0">All farmer applications have been reviewed.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="dash-panel">
                <div class="dash-panel-header">
                    <h2><i class="bi bi-journal-text me-2"></i>Recent Audit Activity</h2>
                    <a href="admin_audit.php" class="btn btn-sm btn-primary">Full log</a>
                </div>
                <?php if ($recentAudit): ?>
                    <?php
                    $auditIcons = [
                        'farmer_approved' => ['bi-patch-check-fill', 'ok'],
                        'farmer_rejected' => ['bi-x-octagon-fill', 'bad'],
                        'farmer_profile_updated' => ['bi-pencil-fill', 'edit'],
                        'listing_approved' => ['bi-bag-check-fill', 'ok'],
                        'listing_rejected' => ['bi-bag-x-fill', 'bad'],
                        'login_success' => ['bi-box-arrow-in-right', 'neutral'],
                        'logout' => ['bi-box-arrow-right', 'neutral'],
                    ];
                    foreach ($recentAudit as $a):
                        $auditAction = str_replace('_', ' ', (string) $a['action']);
                        $auditMeta = $a['entity_type'] . ($a['entity_id'] !== null ? ' #' . $a['entity_id'] : '');
                        [$auditIcon, $auditTone] = $auditIcons[$a['action']] ?? ['bi-shield-check', 'neutral'];
                    ?>
                        <div class="audit-event">
                            <span class="audit-event-icon audit-event-icon--<?= $auditTone ?>" aria-hidden="true"><i class="bi <?= $auditIcon ?>"></i></span>
                            <div class="audit-event-body">
                                <div class="audit-event-title"><?= e(ucwords($auditAction)) ?></div>
                                <div class="audit-event-entity"><?= e($auditMeta) ?></div>
                                <div class="audit-event-meta"><?= e($a['admin_email'] ?? 'admin') ?> &middot; <?= e(date('M j, g:i A', strtotime($a['created_at']))) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state p-4">
                        <h2 class="empty-state-title h6">No audit events yet</h2>
                        <p class="empty-state-description small mb-0">Sensitive admin actions will be recorded here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($role === 'farmer'): ?>
    <!-- Farmer Dashboard -->
    <div class="dash-section-header">
        <h1>Farm Dashboard</h1>
        <p>Manage your harvest, track orders, grow your reach.</p>
        <a href="create_listing.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Listing</a>
    </div>

    <div class="row g-4 mb-4" data-reveal="stagger">
        <?php foreach ($stats as $label => $data): ?>
            <div class="col-12 col-sm-6 col-lg-4">
<div class="stat-card stat-card--<?= e($data['variant'] ?? 'info') ?>" data-reveal="stagger" data-href="<?= e($data['href'] ?? '#') ?>" onclick="if(this.dataset.href&&this.dataset.href!=='#')window.location.href=this.dataset.href;" style="cursor:pointer;">
                    <div class="stat-content">
                        <div class="stat-icon"><i class="bi <?= e($data['icon']) ?>" aria-hidden="true"></i></div>
                        <div class="stat-body">
                            <div class="stat-value" data-count data-stat-target="<?= is_int($data['value']) ? (int) $data['value'] : 0 ?>">
                                <?= e(is_string($data['value']) ? $data['value'] : (string) $data['value']) ?>
                            </div>
                            <div class="stat-label"><?= e($label) ?></div>
                            <div class="stat-trend text-success small"><?= e($data['trend']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Orders -->
    <div class="dash-panel" id="orders">
        <div class="dash-panel-header">
            <h2>Orders</h2>
            <span class="dash-panel-count"><?= $orderTotal ?> total</span>
        </div>
        <?php if ($orders): ?>
        <div class="table-responsive">
            <table class="dash-table align-middle">
                <thead>
                    <tr>
                        <th class="text-nowrap">Order</th>
                        <th>Buyer</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end date-col">Date</th>
                        <th class="text-end action-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        // Truncate order ID to 8-char hex tag
                        $orderId = strtoupper(substr(md5($order['id']), 0, 8));
                        ?>
                        <tr class="order-row clickable-row"
                           tabindex="0" role="button" aria-label="View order details for order #<?= $orderId ?>"
                           data-order-id="<?= $order['id'] ?>"
                           data-buyer-name="<?= e($order['buyer_name']) ?>"
                           data-farm-name="<?= e($order['farm_name']) ?>"
                           data-listing-name="<?= e($order['listing_name']) ?>"
                           data-unit-price="<?= e($order['unit_price']) ?>"
                           data-quantity="<?= e($order['quantity']) ?>"
                           data-delivery-fee="<?= e($order['delivery_fee']) ?>"
                           data-status="<?= e($order['status']) ?>"
                           data-created-at="<?= e($order['created_at']) ?>"
                           data-unit-symbol="<?= e($order['unit_symbol']) ?>"
                           data-total-raw="<?= (float) $order['unit_price'] * (float) $order['quantity'] + (float) $order['delivery_fee'] ?>"
                           data-delivery-method="<?= e($order['delivery_method'] ?? '') ?>">
                            <td class="text-nowrap order-id-tag" data-label="Order">#<?= $orderId ?></td>
                            <td data-label="Buyer"><?= e($order['buyer_name']) ?></td>
                            <td data-label="Item"><?= e($order['listing_name']) ?></td>
                            <td class="text-end" data-label="Qty"><?= $fmtQty($order['quantity'], $order['unit_symbol']) ?></td>
                            <td class="text-end" data-label="Total"><?= order_total($order) ?></td>
                            <td data-label="Status"><?= status_badge($order['status']) ?></td>
                            <td class="text-end date-col text-muted" data-label="Placed"><?= e(date('M j, g:i A', strtotime($order['created_at']))) ?></td>
                            <td class="text-end action-col" data-label="Actions">
                                <a href="/order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
            <?php
            $page = $orderPage;
            $totalPages = $orderPages;
            $pageParam = 'orders_page';
            $param = '';
            $labels = [
                'prev' => t($navCopy['ui'], 'pager_prev'),
                'next' => t($navCopy['ui'], 'pager_next'),
                'page' => t($navCopy['ui'], 'pager_page'),
                'of'   => t($navCopy['ui'], 'pager_of'),
            ];
            include __DIR__ . '/partials/pager.php';
            ?>
        <?php else: ?>
        <?php
        $icon = '<i class="bi bi-inbox" aria-hidden="true"></i>';
        $title = 'No orders yet';
        $description = 'Once buyers find your listings, orders will appear here.';
        $action = ['href' => 'browse.php', 'label' => 'Browse Marketplace', 'class' => 'btn btn-primary btn-sm'];
        require __DIR__ . '/partials/empty-state.php';
        ?>
        <?php endif; ?>
    </div>

    <!-- Analytics summary (full breakdown lives on the analytics page) -->
    <div class="dash-panel mt-4" id="analytics">
        <div class="dash-panel-header">
            <h2><i class="bi bi-graph-up me-2"></i>Analytics</h2>
            <a href="farmer_analytics.php" class="btn btn-sm btn-primary">View full analytics</a>
        </div>
        <div class="dash-panel-body">
            <p class="text-muted small mb-0">Detailed listing quantities and order-by-status breakdowns are on the <a href="farmer_analytics.php">analytics page</a>.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Buyer Dashboard -->
    <div class="row g-4 mb-4" data-reveal="stagger">
        <?php foreach ($stats as $label => $data): ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="stat-card stat-card--<?= e($data['variant'] ?? 'info') ?>" data-reveal="stagger" data-href="<?= e($data['href'] ?? '#') ?>" onclick="if(this.dataset.href&&this.dataset.href!=='#')window.location.href=this.dataset.href;" style="cursor:pointer;">
                    <div class="stat-content">
                        <div class="stat-icon"><i class="bi <?= e($data['icon']) ?>" aria-hidden="true"></i></div>
                        <div class="stat-body">
                            <div class="stat-value" data-count data-stat-target="<?= is_int($data['value']) ? (int) $data['value'] : 0 ?>">
                                <?= e(is_string($data['value']) ? $data['value'] : (string) $data['value']) ?>
                            </div>
                            <div class="stat-label"><?= e($label) ?></div>
                            <div class="stat-trend text-success small"><?= e($data['trend']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Order History -->
    <div class="dash-panel" id="orders">
        <div class="dash-panel-header">
            <h2>Order History</h2>
            <span class="dash-panel-count"><?= $orderTotal ?> orders</span>
        </div>
        <?php if ($orders): ?>
        <div class="table-responsive">
            <table class="dash-table align-middle">
                <thead>
                    <tr>
                        <th class="text-nowrap">Order</th>
                        <th>Farm</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end date-col">Date</th>
                        <th class="text-end action-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        // Truncate order ID to 8-char hex tag
                        $orderId = strtoupper(substr(md5($order['id']), 0, 8));
                        ?>
                        <tr class="order-row clickable-row"
                           tabindex="0" role="button" aria-label="View order details for order #<?= $orderId ?>"
                           data-order-id="<?= $order['id'] ?>"
                           data-buyer-name="<?= e($order['buyer_name']) ?>"
                           data-farm-name="<?= e($order['farm_name']) ?>"
                           data-listing-name="<?= e($order['listing_name']) ?>"
                           data-unit-price="<?= e($order['unit_price']) ?>"
                           data-quantity="<?= e($order['quantity']) ?>"
                           data-delivery-fee="<?= e($order['delivery_fee']) ?>"
                           data-status="<?= e($order['status']) ?>"
                           data-created-at="<?= e($order['created_at']) ?>"
                           data-unit-symbol="<?= e($order['unit_symbol']) ?>"
                           data-total-raw="<?= (float) $order['unit_price'] * (float) $order['quantity'] + (float) $order['delivery_fee'] ?>">
                            <td class="text-nowrap order-id-tag" data-label="Order">#<?= $orderId ?></td>
                            <td data-label="Farm"><?= e($order['farm_name']) ?></td>
                            <td data-label="Item"><?= e($order['listing_name']) ?></td>
                            <td class="text-end" data-label="Qty"><?= $fmtQty($order['quantity'], $order['unit_symbol']) ?></td>
                            <td class="text-end" data-label="Total"><?= order_total($order) ?></td>
                            <td data-label="Status"><?= status_badge($order['status']) ?></td>
                            <td class="text-end date-col text-muted" data-label="Placed"><?= e(date('M j, g:i A', strtotime($order['created_at']))) ?></td>
                            <td class="text-end action-col" data-label="Actions">
                                <a href="/order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
            <?php
            $page = $orderPage;
            $totalPages = $orderPages;
            $pageParam = 'orders_page';
            $param = '';
            $labels = [
                'prev' => t($navCopy['ui'], 'pager_prev'),
                'next' => t($navCopy['ui'], 'pager_next'),
                'page' => t($navCopy['ui'], 'pager_page'),
                'of'   => t($navCopy['ui'], 'pager_of'),
            ];
            include __DIR__ . '/partials/pager.php';
            ?>
        <?php else: ?>
        <?php
        $icon = '<i class="bi bi-basket3" aria-hidden="true"></i>';
        $title = 'No orders yet';
        $description = 'Browse the marketplace to find fresh produce from verified farms.';
        $action = ['href' => 'browse.php', 'label' => 'Browse Market', 'class' => 'btn btn-primary btn-sm'];
        require __DIR__ . '/partials/empty-state.php';
        ?>
        <?php endif; ?>
    </div>

    <!-- Quick Browse: real, live listings instead of hardcoded placeholders -->
    <div class="dash-panel mt-4">
        <div class="dash-panel-header">
            <h2>Discover</h2>
            <a href="browse.php" class="btn btn-sm btn-outline-secondary">Browse all</a>
        </div>
        <div class="horizontal-carousel">
            <?php
              // Latest three active listings, by publication date. This is a
              // "peek" surface, not a second marketplace.
              $discoverStmt = $pdo->query(
                  "SELECT pl.id, pl.name, pl.price_per_unit, qu.symbol AS unit_symbol, "
                  . "pc.name AS category_name, fp.farm_name, fp.municipality_city "
                  . "FROM produce_listings pl "
                  . "JOIN produce_categories pc ON pc.id = pl.category_id "
                  . "JOIN quantity_units qu ON qu.id = pl.unit_id "
                  . "JOIN farmer_profiles fp ON fp.user_id = pl.farmer_id "
                  . "WHERE pl.status = 'active' AND fp.verification_status = 'approved' "
                  . "ORDER BY pl.published_at DESC LIMIT 3"
              );
              $discover = $discoverStmt ? $discoverStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            ?>
            <?php if (!$discover): ?>
                <div class="text-muted small p-3">
                    No listings yet. <a href="create_listing.php">Be the first to post one.</a>
                </div>
            <?php endif; ?>
            <?php foreach ($discover as $d): ?>
                <div class="carousel-item">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2"><?= e($d['name']) ?></h6>
                            <p class="card-text text-muted small">
                                <?= e($d['farm_name']) ?> &middot; <?= e($d['municipality_city']) ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="fw-medium"><?= money($d['price_per_unit']) ?> <small>/ <?= e($d['unit_symbol'] ?? 'kg') ?></small></span>
                                <a class="btn btn-sm btn-outline-primary" href="listing.php?id=<?= (int) $d['id'] ?>">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="carousel-controls mt-3 d-flex justify-content-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="prevSlide" aria-label="Previous items">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="nextSlide" aria-label="Next items">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>

<?php endif; ?>

<!-- Order Detail Drawer -->
<div id="drawer-overlay" class="drawer-overlay" aria-hidden="true"></div>
<div id="drawer" class="drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-label" aria-hidden="true" inert data-csrf="<?= e(csrf_token()) ?>">
  <div class="drawer-header">
    <button type="button" class="btn-close" aria-label="Close drawer">
      <i class="bi bi-x"></i>
    </button>
    <h5 id="drawer-label">Order Details</h5>
  </div>
  <div class="drawer-body">
    <!-- Content: JS-loaded -->
  </div>
</div>
<script>
// Order drawer
document.addEventListener('DOMContentLoaded', function () {
  const drawer = document.getElementById('drawer');
  const overlay = document.getElementById('drawer-overlay');
  if (!drawer || !overlay) return;
  const ROLE = '<?= $role ?>';
  let drawerTrigger = null;
  const drawerFocusables = () => [...drawer.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])')];

  const openDrawer = (orderRow) => {
    drawerTrigger = orderRow;
    const data = orderRow.dataset;
    const status = data.status;
    const deliveryMethod = data.deliveryMethod || '';
    const orderId = data.orderId;

    // Build farmer action buttons by status
    let farmerActions = '';
    if (ROLE === 'farmer') {
      const csrf = drawer.dataset.csrf;
      if (status === 'pending' || status === 'delivery_fee_review') {
        farmerActions += `
          <form method="post" action="/order_action.php" class="mb-2">
            <input type="hidden" name="csrf_token" value="${csrf}">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="action" value="confirm">
            <button type="submit" class="btn btn-primary btn-sm w-100">Confirm Order</button>
          </form>
          <form method="post" action="/order_action.php" class="mb-2">
            <input type="hidden" name="csrf_token" value="${csrf}">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="reason" value="Rejected from dashboard">
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Reject</button>
          </form>`;
      } else if (status === 'confirmed') {
        farmerActions += `
          <form method="post" action="/order_action.php" class="mb-2">
            <input type="hidden" name="csrf_token" value="${csrf}">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="action" value="prepare">
            <button type="submit" class="btn btn-primary btn-sm w-100">Start Preparing</button>
          </form>`;
      } else if (status === 'preparing') {
        farmerActions += `
          <form method="post" action="/order_action.php" class="mb-2">
            <input type="hidden" name="csrf_token" value="${csrf}">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="action" value="ready">
            <button type="submit" class="btn btn-primary btn-sm w-100">Mark Ready</button>
          </form>`;
      } else if (status === 'ready_for_pickup') {
        farmerActions += `
          <form method="post" action="/order_action.php" class="mb-2">
            <input type="hidden" name="csrf_token" value="${csrf}">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="action" value="ship">
            <button type="submit" class="btn btn-primary btn-sm w-100">Out for Delivery</button>
          </form>`;
      } else if (deliveryMethod === 'farmer_delivery' && status === 'out_for_delivery') {
        farmerActions += `
          <form method="post" action="/order_action.php" class="mb-2">
            <input type="hidden" name="csrf_token" value="${csrf}">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="action" value="complete">
            <button type="submit" class="btn btn-success btn-sm w-100">Mark Delivered</button>
          </form>`;
      }
    }

    // Populate drawer
    drawer.querySelector('.drawer-body').innerHTML = `
      <div class="drawer-section">
        <h6 class="drawer-section-title">Order Info</h6>
        <div class="drawer-section-content">
          <div class="drawer-row">
            <span class="drawer-row-label">Product:</span>
            <span class="drawer-row-value">${data.listingName || 'N/A'}</span>
          </div>
          <div class="drawer-row">
            <span class="drawer-row-label">Status:</span>
            <span class="drawer-row-value">${status}</span>
          </div>
          <div class="drawer-row">
            <span class="drawer-row-label">Delivery:</span>
            <span class="drawer-row-value">${deliveryMethod === 'farmer_delivery' ? 'Farmer Delivery' : 'Pickup'}</span>
          </div>
        </div>
      </div>
      <div class="drawer-divider"></div>
      <div class="drawer-section">
        <h6 class="drawer-section-title">Payout</h6>
        <div class="drawer-section-content">
          <div class="drawer-row">
            <span class="drawer-row-label">Unit Price:</span>
            <span class="drawer-row-value">â‚±${parseFloat(data.unitPrice || 0).toFixed(2)}</span>
          </div>
          <div class="drawer-row">
            <span class="drawer-row-label">Quantity:</span>
            <span class="drawer-row-value">${data.quantity} ${data.unitSymbol || ''}</span>
          </div>
          <div class="drawer-row">
            <span class="drawer-row-label">Delivery Fee:</span>
            <span class="drawer-row-value">â‚±${parseFloat(data.deliveryFee || 0).toFixed(2)}</span>
          </div>
          <div class="drawer-row">
            <span class="drawer-row-label">Total:</span>
            <span class="drawer-row-value"><strong>â‚±${parseFloat(data.totalRaw || 0).toFixed(2)}</strong></span>
          </div>
        </div>
      </div>
      ${farmerActions ? '<div class="drawer-divider"></div><div class="drawer-section"><h6 class="drawer-section-title">Quick Actions</h6><div class="drawer-section-content">' + farmerActions + '</div></div>' : ''}
      <div class="drawer-divider"></div>
      <a href="/order.php?id=${orderId}" class="btn btn-outline-primary btn-sm w-100">
        <i class="bi bi-eye me-1"></i>View Full Order
      </a>
    `;
    drawer.setAttribute('aria-hidden', 'false');
    drawer.removeAttribute('inert');
    overlay.setAttribute('aria-hidden', 'false');
    drawer.classList.add('open');
    overlay.classList.add('visible');
    requestAnimationFrame(() => drawer.querySelector('.btn-close')?.focus());
  };

  const closeDrawer = () => {
    drawer.setAttribute('aria-hidden', 'true');
    drawer.setAttribute('inert', '');
    overlay.setAttribute('aria-hidden', 'true');
    drawer.classList.remove('open');
    overlay.classList.remove('visible');
    requestAnimationFrame(() => drawerTrigger?.focus());
  };

  // Delegated click for order rows
  document.addEventListener('click', function (e) {
    // Ignore clicks on links/buttons/forms
    if (e.target.closest('a') || e.target.closest('button') || e.target.closest('form')) return;
    const orderRow = e.target.closest('.order-row.clickable-row');
    if (orderRow) {
      e.preventDefault();
      openDrawer(orderRow);
    }
  });

  // Close drawer on overlay/btn-close/Escape
  overlay.addEventListener('click', closeDrawer);
  drawer.querySelector('.btn-close').addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) {
    if (!drawer.classList.contains('open')) return;
    if (e.key === 'Escape') return closeDrawer();
    if (e.key !== 'Tab') return;
    const items = drawerFocusables();
    if (!items.length) return;
    if (e.shiftKey && document.activeElement === items[0]) { e.preventDefault(); items.at(-1).focus(); }
    else if (!e.shiftKey && document.activeElement === items.at(-1)) { e.preventDefault(); items[0].focus(); }
  });
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && document.activeElement?.matches('.order-row.clickable-row')) {
      e.preventDefault();
      openDrawer(document.activeElement);
    }
  });
});
</script>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>