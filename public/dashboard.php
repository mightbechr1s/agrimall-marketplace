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

    $usersNew = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= "' . $weekAgo . '"')->fetchColumn();
    $listingsNew = (int) $pdo->query('SELECT COUNT(*) FROM produce_listings WHERE created_at >= "' . $weekAgo . '"')->fetchColumn();
    $ordersNew = (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE created_at >= "' . $weekAgo . '"')->fetchColumn();

    $stats = [
        'Users' => [
            'value' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'trend' => $usersNew . ' new this week',
            'icon' => 'bi-people',
            'variant' => 'info'
        ],
        'Pending Verifications' => [
            'value' => (int) $pdo->query('SELECT COUNT(*) FROM farmer_profiles WHERE verification_status = \'pending\'')->fetchColumn(),
            'trend' => 'awaiting review',
            'icon' => 'bi-patch-check-fill',
            'variant' => 'warning'
        ],
        'Active Listings' => [
            'value' => (int) $pdo->query('SELECT COUNT(*) FROM produce_listings WHERE status = \'active\'')->fetchColumn(),
            'trend' => $listingsNew . ' new this week',
            'icon' => 'bi-basket3-fill',
            'variant' => 'success'
        ],
        'Pending Orders' => [
            'value' => (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE status IN (\'pending\',\'delivery_fee_review\')')->fetchColumn(),
            'trend' => $ordersNew . ' placed this week',
            'icon' => 'bi-clock-history',
            'variant' => 'warning'
        ],
    ];
    $orders = $pdo->query('SELECT * FROM v_order_summary ORDER BY created_at DESC LIMIT 10')->fetchAll();

    $pendingFarmers = $pdo->query('SELECT u.id AS user_id, u.created_at, fp.farm_name, fp.municipality_city, fp.province
                                   FROM farmer_profiles fp
                                   JOIN users u ON u.id = fp.user_id
                                   WHERE fp.verification_status = \'pending\'
                                   ORDER BY u.created_at ASC LIMIT 5')->fetchAll();
    $recentAudit = $pdo->query('SELECT al.action, al.entity_type, al.entity_id, al.created_at, u.email AS admin_email
                                FROM admin_audit_log al
                                LEFT JOIN users u ON u.id = al.admin_id
                                ORDER BY al.created_at DESC LIMIT 8')->fetchAll();

} elseif ($role === 'farmer') {
    $pdo = DB::pdo();
    $mine = $pdo->prepare('SELECT COUNT(*) FROM produce_listings WHERE farmer_id = :id');
    $mine->execute(['id' => $userId]);
    $activeCount = $pdo->prepare('SELECT COUNT(*) FROM produce_listings WHERE farmer_id = :id AND status = \'active\'');
    $activeCount->execute(['id' => $userId]);
    $pendingOrders = $pdo->prepare('SELECT COUNT(*) FROM v_order_summary WHERE farmer_id = :id AND status IN (\'pending\',\'confirmed\',\'preparing\')');
    $pendingOrders->execute(['id' => $userId]);

    // Total earnings and volume sold
    $earningsStmt = $pdo->prepare('SELECT COALESCE(SUM(o.unit_price * o.quantity + o.delivery_fee), 0) FROM orders o JOIN v_order_summary vos ON o.id = vos.id WHERE vos.farmer_id = :id AND vos.status NOT IN (\'cancelled\',\'rejected\')');
    $earningsStmt->execute(['id' => $userId]);
    $totalEarnings = money((float) $earningsStmt->fetchColumn());

    $volumeStmt = $pdo->prepare('SELECT COALESCE(SUM(o.quantity), 0) FROM orders o JOIN v_order_summary vos ON o.id = vos.id WHERE vos.farmer_id = :id AND vos.status NOT IN (\'cancelled\',\'rejected\')');
    $volumeStmt->execute(['id' => $userId]);
    $totalVolume = rtrim(rtrim(number_format((float) $volumeStmt->fetchColumn(), 2), '0'), '.') . ' units';

    $listingSummaryStmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(available_quantity), 0) AS qty
         FROM produce_listings WHERE farmer_id = :id GROUP BY status'
    );
    $listingSummaryStmt->execute(['id' => $userId]);
    $listingSummary = $listingSummaryStmt->fetchAll();

    $orderSummaryStmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS cnt,
                COALESCE(SUM(o.unit_price * o.quantity), 0) AS produce,
                COALESCE(SUM(o.delivery_fee), 0) AS fees
         FROM orders o WHERE o.farmer_id = :id GROUP BY status'
    );
    $orderSummaryStmt->execute(['id' => $userId]);
    $orderSummary = $orderSummaryStmt->fetchAll();
    $orderSummaryByStatus = [];
    foreach ($orderSummary as $row) {
        $orderSummaryByStatus[$row['status']] = $row;
    }
    $completed = (int) ($orderSummaryByStatus['completed']['cnt'] ?? 0);
    $closed = (int) ($orderSummaryByStatus['cancelled']['cnt'] ?? 0) + (int) ($orderSummaryByStatus['rejected']['cnt'] ?? 0);

    $stats = [
        'Total Earnings' => [
            'value' => $totalEarnings,
            'trend' => $completed . ' completed order' . ($completed === 1 ? '' : 's'),
            'icon' => 'bi-currency-dollar',
            'type' => 'money',
            'variant' => 'success'
        ],
        'Volume Sold' => [
            'value' => $totalVolume,
            'trend' => $closed . ' cancelled/rejected',
            'icon' => 'bi-box-seam',
            'variant' => 'info'
        ],
        'Pending Orders' => [
            'value' => (int) $pendingOrders->fetchColumn(),
            'trend' => 'awaiting action',
            'icon' => 'bi-clock-history',
            'variant' => 'warning'
        ],
    ];

    $ls = $pdo->prepare(
        'SELECT pl.*, pc.name AS category_name, qu.symbol AS unit_symbol,
                (SELECT li.image_path FROM listing_images li
                  WHERE li.listing_id = pl.id AND li.is_primary = 1
                  ORDER BY li.sort_order LIMIT 1) AS primary_image
         FROM produce_listings pl
         JOIN produce_categories pc ON pl.category_id = pc.id
         JOIN quantity_units qu ON pl.unit_id = qu.id
         WHERE pl.farmer_id = :id AND pl.status <> \'archived\'
         ORDER BY pl.created_at DESC'
    );
    $ls->execute(['id' => $userId]);
    $farmerListings = $ls->fetchAll();

    $os = $pdo->prepare('SELECT * FROM v_order_summary WHERE farmer_id = :id ORDER BY created_at DESC');
    $os->execute(['id' => $userId]);
    $orders = $os->fetchAll();

} else {
    // Buyer
    $pdo = DB::pdo();
    $os = $pdo->prepare('SELECT * FROM v_order_summary WHERE buyer_id = :id ORDER BY created_at DESC');
    $os->execute(['id' => $userId]);
    $orders = $os->fetchAll();
    $activeOrders = 0;
    foreach ($orders as $o) {
        if (!in_array($o['status'], ['completed', 'cancelled', 'rejected'])) $activeOrders++;
    }
    $stats = [
        'Total Orders' => [
            'value' => count($orders),
            'trend' => '+7%',
            'icon' => 'bi-receipt',
            'variant' => 'info'
        ],
        'Active Orders' => [
            'value' => $activeOrders,
            'trend' => '+5%',
            'icon' => 'bi-clock',
            'variant' => 'warning'
        ],
        'Completed' => [
            'value' => count($orders) - $activeOrders,
            'trend' => '+3%',
            'icon' => 'bi-check-circle',
            'variant' => 'success'
        ],
    ];
}

require __DIR__ . '/partials/dashboard-layout.php';
?>

<?php if ($role === 'admin'): ?>
    <!-- Admin Dashboard -->
    <div class="dash-section-header">
        <h1>Platform Overview</h1>
        <p>Monitor AniMAL marketplace activity across all users and listings.</p>
    </div>

    <div class="row g-4 mb-4" data-reveal="stagger">
        <?php foreach ($stats as $label => $data): ?>
            <div class="col-6 col-lg-3">
<div class="stat-card stat-card--<?= e($data['variant'] ?? 'info') ?>" data-reveal="stagger">
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

    <!-- Farmer Verifications + Audit Activity -->
    <div class="row g-4">
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
            <div class="col-6 col-lg-4">
<div class="stat-card stat-card--<?= e($data['variant'] ?? 'info') ?>" data-reveal="stagger">
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

    <!-- My Listings -->
    <div class="dash-panel" id="listings">
        <div class="dash-panel-header">
            <h2>My Listings</h2>
            <a href="create_listing.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New</a>
        </div>
        <?php if ($farmerListings): ?>
        <div class="grid" data-reveal="stagger" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
            <?php foreach ($farmerListings as $l): ?>
              <?php
              $listing = [
                'id' => (int) $l['id'],
                'title' => $l['name'],
                'description' => $l['description'] ?? '',
                'price' => (float) $l['price_per_unit'],
                'unit' => $l['unit_symbol'] ?? 'kg',
                'category' => $l['category_name'] ?? 'Produce',
                'farmer_name' => 'You',
                'farmer_slug' => '',
                'image_path' => $l['primary_image'] ?? fallback_produce_image(),
                'is_fresh' => $l['status'] === 'active',
                'is_saved' => false,
                'status' => $l['status'],
                'qty_available' => (int) ($l['available_quantity'] ?? 0),
              ];
              $mode = 'farmer';
              include __DIR__ . '/partials/listing-card.php';
              ?>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon" aria-hidden="true">
            <path d="M5.757 1.071a.5.5 0 0 1 .172.686L3.383 6h9.234l1.12-4.243a.5.5 0 0 1 .172-.686zM.5 8a.5.5 0 0 1 .5.5V13a1.5 1.5 0 0 0 1.5 1.5h9a1.5 1.5 0 0 0 1.5-1.5V8.5a.5.5 0 0 1 1 0v4.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 0.5 12.5V8.5a.5.5 0 0 1 .5-.5z"/>
          </svg>
          <h2 class="empty-state-title">No listings yet</h2>
          <p class="empty-state-description">Start selling your harvest directly to buyers.</p>
          <a href="create_listing.php" class="btn btn-primary">Create Your First Listing</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Orders -->
    <div class="dash-panel" id="orders">
        <div class="dash-panel-header">
            <h2>Orders</h2>
            <span class="dash-panel-count"><?= count($orders) ?> total</span>
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
                            <td class="text-nowrap order-id-tag">#<?= $orderId ?></td>
                            <td><?= e($order['buyer_name']) ?></td>
                            <td><?= e($order['listing_name']) ?></td>
                            <td class="text-end"><?= $fmtQty($order['quantity'], $order['unit_symbol']) ?></td>
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
          <h2 class="empty-state-title">No orders yet</h2>
          <p class="empty-state-description">Once buyers find your listings, orders will appear here.</p>
          <a href="browse.php" class="btn btn-primary">Browse Marketplace</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Analytics: listings & orders summary -->
    <?php
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
    ?>
    <div class="dash-panel mt-4" id="analytics">
        <div class="dash-panel-header">
            <h2><i class="bi bi-graph-up me-2"></i>Analytics</h2>
            <span class="dash-panel-count">Generated <?= e(date('M j, Y \a\t g:i A')) ?></span>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                <h3 class="fs-6 fw-semibold mb-2">Listing Quantities</h3>
                <p class="text-muted small mb-2">Quantities per produce type; totals mix unit types (e.g. bundle, kg).</p>
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
                                <th class="text-end"><?= count($farmerListings) ?></th>
                                <th class="text-end"><?= rtrim(rtrim(number_format(array_sum(array_map(function ($r) { return (float) $r['available_quantity']; }, $farmerListings)), 2), '0'), '.') ?></th>
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
                                <th class="text-end"><?= count($orders) ?></th>
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

<?php else: ?>
    <!-- Buyer Dashboard -->
    <div class="dash-section-header">
        <h1>My Orders</h1>
        <p>Track your orders, discover fresh produce from verified farms.</p>
        <a href="browse.php" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Browse Market</a>
    </div>

    <div class="row g-4 mb-4" data-reveal="stagger">
        <?php foreach ($stats as $label => $data): ?>
            <div class="col-6 col-lg-4">
                <div class="stat-card stat-card--<?= e($data['variant'] ?? 'info') ?>" data-reveal="stagger">
                    <div class="stat-content">
                        <div class="stat-icon"><i class="bi <?= e($data['icon']) ?>" aria-hidden="true"></i></div>
                        <div class="stat-body">
                            <div class="stat-value" data-count data-stat-target="<?= is_int($data['value']) ? (int) $data['value'] : 0 ?>">
                                <?= e(is_string($data['value']) ? $data['value'] : (string) $data['value']) ?>
                            </div>
                            <div class="stat-label"><?= e($label) ?></div>
                            <div class="stat-trend text-success small"><?= $data['trend'] ?> this week</div>
                            <!-- SVG sparkline -->
                            <div class="stat-sparkline mt-2">
                                <svg width="60" height="20" viewBox="0 0 60 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="none" stroke="currentColor" stroke-width="1.5"
                                          d="M5,10 Q15,5 25,10 T45,10 L55,10"/>
                                </svg>
                            </div>
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
            <span class="dash-panel-count"><?= count($orders) ?> orders</span>
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
                            <td class="text-nowrap order-id-tag">#<?= $orderId ?></td>
                            <td><?= e($order['farm_name']) ?></td>
                            <td><?= e($order['listing_name']) ?></td>
                            <td class="text-end"><?= $fmtQty($order['quantity'], $order['unit_symbol']) ?></td>
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
          <h2 class="empty-state-title">No orders yet</h2>
          <p class="empty-state-description">Browse the marketplace to find fresh produce from verified farms.</p>
          <a href="browse.php" class="btn btn-primary">Browse Marketplace</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Browse -->
    <div class="dash-panel">
        <div class="dash-panel-header"><h2>Discover</h2></div>
        <div class="horizontal-carousel overflow-hidden">
            <div class="carousel-slide d-flex gap-3" id="discoverCarousel">
                <!-- Carousel: JS-populated -->
                <div class="carousel-item flex-shrink-0">
                    <div class="card h-100 ">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-1">Organic Kale</h6>
                            <p class="card-text text-muted small">Fresh from Batangas</p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="fw-medium">₱85/bundle</span>
                                <button class="btn btn-sm btn-outline-primary fast-add-btn">
                                    <i class="bi bi-plus-lg me-1"></i>Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item flex-shrink-0">
                    <div class="card h-100 ">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-1">Sweet Mangoes</h6>
                            <p class="card-text text-muted small">Ripe from Cebu</p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="fw-medium">₱120/kg</span>
                                <button class="btn btn-sm btn-outline-primary fast-add-btn">
                                    <i class="bi bi-plus-lg me-1"></i>Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item flex-shrink-0">
                    <div class="card h-100 ">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-1">Free-Range Eggs</h6>
                            <p class="card-text text-muted small">Daily from Pangasinan</p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="fw-medium">₱70/dozen</span>
                                <button class="btn btn-sm btn-outline-primary fast-add-btn">
                                    <i class="bi bi-plus-lg me-1"></i>Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="carousel-controls mt-3">
                <button class="btn btn-sm btn-outline-secondary me-2" id="prevSlide" aria-label="Previous items">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="nextSlide" aria-label="Next items">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Saved Listings -->
    <div class="dash-panel mt-4" id="saved">
        <div class="dash-panel-header">
            <h2>Saved Listings</h2>
        </div>
        <?php
        $savedStmt = DB::pdo()->prepare(
            'SELECT sl.listing_id, pl.name, pl.price_per_unit, pl.description, fp.farm_name, qu.symbol,
                    (SELECT li.image_path FROM listing_images li
                      WHERE li.listing_id = pl.id AND li.is_primary = 1
                      ORDER BY li.sort_order LIMIT 1) AS primary_image,
                    pc.name AS category_name, sl.created_at
             FROM saved_listings sl
             JOIN produce_listings pl ON sl.listing_id = pl.id
             JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
             JOIN quantity_units qu ON pl.unit_id = qu.id
             JOIN produce_categories pc ON pl.category_id = pc.id
             WHERE sl.user_id = :user AND pl.status = \'active\'
             ORDER BY sl.created_at DESC'
        );
        $savedStmt->execute(['user' => current_user_id()]);
        $savedListings = $savedStmt->fetchAll();
        ?>
        <?php if (!$savedListings): ?>
            <div class="dash-empty">
                <i class="bi bi-heart" aria-hidden="true"></i>
                <p>Nothing saved yet. Tap the heart on a listing to keep it here for later.</p>
                <a class="btn btn-primary" href="browse.php">Browse Listings</a>
            </div>
        <?php else: ?>
            <div class="grid" data-reveal="stagger" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                <?php foreach ($savedListings as $saved): ?>
                    <?php
                    $listing = [
                      'id' => (int) $saved['listing_id'],
                      'title' => $saved['name'],
                      'description' => $saved['description'] ?? '',
                      'price' => (float) $saved['price_per_unit'],
                      'unit' => $saved['symbol'] ?? 'kg',
                      'category' => $saved['category_name'] ?? 'Produce',
                      'farmer_name' => $saved['farm_name'],
                      'farmer_slug' => '',
                      'image_path' => $saved['primary_image'] ?: fallback_produce_image(),
                      'is_fresh' => true,
                      'is_saved' => true,
                      'qty_available' => 999,
                    ];
                    $mode = 'grid';
                    $showActions = false;
                    include __DIR__ . '/partials/listing-card.php';
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
            <span class="drawer-row-value">₱${parseFloat(data.unitPrice || 0).toFixed(2)}</span>
          </div>
          <div class="drawer-row">
            <span class="drawer-row-label">Quantity:</span>
            <span class="drawer-row-value">${data.quantity} ${data.unitSymbol || ''}</span>
          </div>
          <div class="drawer-row">
            <span class="drawer-row-label">Delivery Fee:</span>
            <span class="drawer-row-value">₱${parseFloat(data.deliveryFee || 0).toFixed(2)}</span>
          </div>
          <div class="drawer-row">
            <span class="drawer-row-label">Total:</span>
            <span class="drawer-row-value"><strong>₱${parseFloat(data.totalRaw || 0).toFixed(2)}</strong></span>
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