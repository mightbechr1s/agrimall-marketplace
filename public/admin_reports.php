<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$pdo = DB::pdo();

// Report whitelist (FR-REP-001,002,003,007,008,009; FR-REP-004 is the farmer-side summary).
$reports = [
    'registration'   => ['title' => 'Registration Report',    'ref' => 'FR-REP-001'],
    'listings'       => ['title' => 'Listing Report',         'ref' => 'FR-REP-002'],
    'orders'         => ['title' => 'Order Report',           'ref' => 'FR-REP-003'],
    'verification'   => ['title' => 'Farmer Verification Report', 'ref' => 'FR-REP-007'],
    'fulfillment'    => ['title' => 'Fulfillment Value Report',   'ref' => 'FR-REP-008'],
    'cancellations'  => ['title' => 'Cancellation Summary',   'ref' => 'FR-REP-009'],
];
$type = $_GET['t'] ?? 'registration';
if (!isset($reports[$type])) {
    $type = 'registration';
}
$reportTitle = $reports[$type]['title'];
$reportRef = $reports[$type]['ref'];

function is_valid_date(?string $value): bool
{
    return is_string($value) && $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
}

function fmt_qty($value): string
{
    $n = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    return $n === '' || $n === '-' ? '0' : $n;
}

function p($value): string
{
    return '₱' . number_format((float) $value, 2);
}

// Adds >= :x and < :y+1day bound params for a column.
function bind_date_range(array &$whereClauses, array &$params, string $column, ?string $from, ?string $to, string $prefix): void
{
    if (is_valid_date($from)) {
        $whereClauses[] = "$column >= :{$prefix}_from";
        $params["{$prefix}_from"] = $from . ' 00:00:00';
    }
    if (is_valid_date($to)) {
        $whereClauses[] = "$column < :{$prefix}_to_plus";
        $params["{$prefix}_to_plus"] = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
    }
}

$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$diffFrom = trim((string) ($_GET['diff_from'] ?? ''));
$diffTo = trim((string) ($_GET['diff_to'] ?? ''));

// Dropdown source data; loaded even when the result set is empty.
$roles = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Admin'];
$categories = $pdo->query('SELECT id, name FROM produce_categories ORDER BY name')->fetchAll();
$listingStatuses = ['draft', 'active', 'paused', 'sold_out', 'archived'];
$orderStatuses = ['pending', 'delivery_fee_review', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'rejected', 'cancelled'];
$verifyStatuses = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$methods = ['pickup' => 'Pickup', 'farmer_delivery' => 'Farmer Delivery'];
$cancelRoles = ['buyer' => 'Buyer', 'farmer' => 'Farmer', 'admin' => 'Admin'];
$farmers = $pdo->query(
    'SELECT u.id, COALESCE(fp.farm_name, u.email) AS label
     FROM users u LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
     WHERE u.role = \'farmer\' ORDER BY label'
)->fetchAll();
$buyers = $pdo->query(
    'SELECT u.id, COALESCE(bp.full_name, u.email) AS label
     FROM users u LEFT JOIN buyer_profiles bp ON bp.user_id = u.id
     WHERE u.role = \'buyer\' ORDER BY label'
)->fetchAll();
$reviewers = $pdo->query(
    'SELECT DISTINCT u.id, u.email FROM farmer_profiles fp
     JOIN users u ON u.id = fp.verified_by WHERE fp.verified_by IS NOT NULL ORDER BY u.email'
)->fetchAll();
$stages = $pdo->query(
    'SELECT DISTINCT from_status FROM order_status_history
     WHERE to_status = \'cancelled\' AND from_status IS NOT NULL ORDER BY from_status'
)->fetchAll(PDO::FETCH_COLUMN);

// ---- Filters ----
$filters = [];
$rows = [];
$totals = null;

if ($type === 'registration') {
    $role = (string) ($_GET['role'] ?? '');
    $where = [];
    $params = [];
    bind_date_range($where, $params, 'created_at', $from, $to, 'reg');
    if ($role !== '' && array_key_exists($role, $roles)) {
        $where[] = 'role = :role';
        $params['role'] = $role;
    }
    if ($where) {
        $sql = 'SELECT id, email, role, status, created_at FROM users WHERE ' . implode(' AND ', $where);
    } else {
        $sql = 'SELECT id, email, role, status, created_at FROM users';
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $filterLabel = $role !== '' ? 'role ' . $role : null;
    $filters[] = 'Role: ' . ($roles[$role] ?? 'all');
    if ($from !== '' && is_valid_date($from)) $filters[] = 'From: ' . $from;
    if ($to !== '' && is_valid_date($to)) $filters[] = 'To: ' . $to;
    if ($role === '' && $from === '' && $to === '') $filters = [];

} elseif ($type === 'listings') {
    $category = trim((string) ($_GET['category'] ?? ''));
    $farmer = trim((string) ($_GET['farmer'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $where = [];
    $params = [];
    bind_date_range($where, $params, 'l.published_at', $from, $to, 'lst');
    if ($category !== '' && ctype_digit($category)) {
        $where[] = 'l.category_id = :category';
        $params['category'] = (int) $category;
    }
    if ($farmer !== '' && ctype_digit($farmer)) {
        $where[] = 'l.farmer_id = :farmer';
        $params['farmer'] = (int) $farmer;
    }
    if ($status !== '' && in_array($status, $listingStatuses, true)) {
        $where[] = 'l.status = :status';
        $params['status'] = $status;
    }
    $sql = 'SELECT l.id, l.name, l.price_per_unit, l.available_quantity, l.status, l.published_at,
                   c.name AS category, u.symbol AS unit_symbol, COALESCE(fp.farm_name, ue.email) AS farmer
            FROM produce_listings l
            JOIN produce_categories c ON c.id = l.category_id
            LEFT JOIN quantity_units u ON u.id = l.unit_id
            LEFT JOIN users ue ON ue.id = l.farmer_id
            LEFT JOIN farmer_profiles fp ON fp.user_id = l.farmer_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY l.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $catName = null;
    foreach ($categories as $c) { if ((string) $c['id'] === $category) $catName = $c['name']; }
    $farmerName = null;
    foreach ($farmers as $f) { if ((string) $f['id'] === $farmer) $farmerName = $f['label']; }
    $filters[] = 'Category: ' . ($catName ?? 'all');
    $filters[] = 'Farmer: ' . ($farmerName ?? 'all');
    $filters[] = 'Status: ' . ($status !== '' ? $status : 'all');
    if ($from !== '' && is_valid_date($from)) $filters[] = 'Published from: ' . $from;
    if ($to !== '' && is_valid_date($to)) $filters[] = 'Published to: ' . $to;
    $filters = array_values(array_filter($filters, fn ($f) => !str_ends_with($f, ': all')));

} elseif ($type === 'orders') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $farmer = trim((string) ($_GET['farmer'] ?? ''));
    $buyer = trim((string) ($_GET['buyer'] ?? ''));
    $where = [];
    $params = [];
    bind_date_range($where, $params, 'o.created_at', $from, $to, 'ord');
    if ($status !== '' && in_array($status, $orderStatuses, true)) {
        $where[] = 'o.status = :status';
        $params['status'] = $status;
    }
    if ($farmer !== '' && ctype_digit($farmer)) {
        $where[] = 'o.farmer_id = :farmer';
        $params['farmer'] = (int) $farmer;
    }
    if ($buyer !== '' && ctype_digit($buyer)) {
        $where[] = 'o.buyer_id = :buyer';
        $params['buyer'] = (int) $buyer;
    }
    $sql = 'SELECT o.*, pl.name AS listing, pl.unit_id,
                   COALESCE(bp.full_name, ub.email) AS buyer, COALESCE(fp.farm_name, uf.email) AS farmer,
                   u.symbol AS unit_symbol,
                   (o.quantity * o.unit_price) AS produce_subtotal
            FROM orders o
            JOIN produce_listings pl ON pl.id = o.listing_id
            LEFT JOIN quantity_units u ON u.id = pl.unit_id
            LEFT JOIN users ub ON ub.id = o.buyer_id
            LEFT JOIN buyer_profiles bp ON bp.user_id = o.buyer_id
            LEFT JOIN users uf ON uf.id = o.farmer_id
            LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY o.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $farmerName = null;
    foreach ($farmers as $f) { if ((string) $f['id'] === $farmer) $farmerName = $f['label']; }
    $buyerName = null;
    foreach ($buyers as $b) { if ((string) $b['id'] === $buyer) $buyerName = $b['label']; }
    $filters[] = 'Status: ' . ($status !== '' ? $status : 'all');
    $filters[] = 'Farmer: ' . ($farmerName ?? 'all');
    $filters[] = 'Buyer: ' . ($buyerName ?? 'all');
    if ($from !== '' && is_valid_date($from)) $filters[] = 'From: ' . $from;
    if ($to !== '' && is_valid_date($to)) $filters[] = 'To: ' . $to;
    $filters = array_values(array_filter($filters, fn ($f) => !str_ends_with($f, ': all')));

} elseif ($type === 'verification') {
    $vStatus = trim((string) ($_GET['vstatus'] ?? ''));
    $reviewer = trim((string) ($_GET['reviewer'] ?? ''));
    $where = [];
    $params = [];
    bind_date_range($where, $params, 'fp.created_at', $from, $to, 'sub');
    bind_date_range($where, $params, 'fp.verified_at', $diffFrom, $diffTo, 'rev');
    if ($vStatus !== '' && array_key_exists($vStatus, $verifyStatuses)) {
        $where[] = 'fp.verification_status = :vstatus';
        $params['vstatus'] = $vStatus;
    }
    if ($reviewer !== '' && ctype_digit($reviewer)) {
        $where[] = 'fp.verified_by = :reviewer';
        $params['reviewer'] = (int) $reviewer;
    }
    $sql = 'SELECT fp.*, u.email, ue.email AS reviewer_email
            FROM farmer_profiles fp
            JOIN users u ON u.id = fp.user_id
            LEFT JOIN users ue ON ue.id = fp.verified_by';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY fp.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $reviewerName = null;
    foreach ($reviewers as $r) { if ((string) $r['id'] === $reviewer) $reviewerName = $r['email']; }
    $filters[] = 'Status: ' . ($verifyStatuses[$vStatus] ?? 'all');
    $filters[] = 'Reviewer: ' . ($reviewerName ?? 'all');
    if ($from !== '' && is_valid_date($from)) $filters[] = 'Submitted from: ' . $from;
    if ($to !== '' && is_valid_date($to)) $filters[] = 'Submitted to: ' . $to;
    if ($diffFrom !== '' && is_valid_date($diffFrom)) $filters[] = 'Reviewed from: ' . $diffFrom;
    if ($diffTo !== '' && is_valid_date($diffTo)) $filters[] = 'Reviewed to: ' . $diffTo;
    $filters = array_values(array_filter($filters, fn ($f) => !str_ends_with($f, ': all')));

} elseif ($type === 'fulfillment') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $farmer = trim((string) ($_GET['farmer'] ?? ''));
    $method = trim((string) ($_GET['method'] ?? ''));
    $where = [];
    $params = [];
    bind_date_range($where, $params, 'o.created_at', $from, $to, 'ful');
    if ($status !== '' && in_array($status, $orderStatuses, true)) {
        $where[] = 'o.status = :status';
        $params['status'] = $status;
    }
    if ($farmer !== '' && ctype_digit($farmer)) {
        $where[] = 'o.farmer_id = :farmer';
        $params['farmer'] = (int) $farmer;
    }
    if ($method !== '' && array_key_exists($method, $methods)) {
        $where[] = 'o.delivery_method = :method';
        $params['method'] = $method;
    }
    $sql = 'SELECT o.order_number, o.created_at, o.quantity, o.unit_price, o.delivery_fee, o.status, o.delivery_method,
                   pl.name AS listing, COALESCE(fp.farm_name, uf.email) AS farmer, u.symbol AS unit_symbol,
                   (o.quantity * o.unit_price) AS produce_subtotal,
                   (o.quantity * o.unit_price + o.delivery_fee) AS final_total
            FROM orders o
            JOIN produce_listings pl ON pl.id = o.listing_id
            LEFT JOIN quantity_units u ON u.id = pl.unit_id
            LEFT JOIN users uf ON uf.id = o.farmer_id
            LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY o.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $totals = [
        'count' => 0, 'subtotal' => 0.0, 'fees' => 0.0, 'final' => 0.0,
    ];
    foreach ($rows as $r) {
        $totals['count']++;
        $totals['subtotal'] += (float) $r['produce_subtotal'];
        $totals['fees'] += (float) $r['delivery_fee'];
        $totals['final'] += (float) $r['final_total'];
    }
    $farmerName = null;
    foreach ($farmers as $f) { if ((string) $f['id'] === $farmer) $farmerName = $f['label']; }
    $filters[] = 'Status: ' . ($status !== '' ? $status : 'all');
    $filters[] = 'Farmer: ' . ($farmerName ?? 'all');
    $filters[] = 'Method: ' . ($method !== '' ? ($methods[$method] ?? $method) : 'all');
    if ($from !== '' && is_valid_date($from)) $filters[] = 'From: ' . $from;
    if ($to !== '' && is_valid_date($to)) $filters[] = 'To: ' . $to;
    $filters = array_values(array_filter($filters, fn ($f) => !str_ends_with($f, ': all')));

} elseif ($type === 'cancellations') {
    $cRole = trim((string) ($_GET['crole'] ?? ''));
    $stage = trim((string) ($_GET['stage'] ?? ''));
    $farmer = trim((string) ($_GET['farmer'] ?? ''));
    $buyer = trim((string) ($_GET['buyer'] ?? ''));
    $where = [];
    $params = [];
    bind_date_range($where, $params, 'o.cancelled_at', $from, $to, 'cxl');
    if ($cRole !== '' && array_key_exists($cRole, $cancelRoles)) {
        $where[] = 'o.cancelled_by_role = :crole';
        $params['crole'] = $cRole;
    }
    if ($stage !== '' && in_array($stage, $stages, true)) {
        $where[] = 'osh.from_status = :stage';
        $params['stage'] = $stage;
    }
    if ($farmer !== '' && ctype_digit($farmer)) {
        $where[] = 'o.farmer_id = :farmer';
        $params['farmer'] = (int) $farmer;
    }
    if ($buyer !== '' && ctype_digit($buyer)) {
        $where[] = 'o.buyer_id = :buyer';
        $params['buyer'] = (int) $buyer;
    }
    $sql = 'SELECT o.order_number, o.cancelled_at, o.cancelled_by_role, o.cancellation_reason,
                   COALESCE(bp.full_name, ub.email) AS buyer, COALESCE(fp.farm_name, uf.email) AS farmer,
                   osh.from_status AS stage
            FROM orders o
            LEFT JOIN order_status_history osh ON osh.order_id = o.id AND osh.to_status = \'cancelled\'
            LEFT JOIN users ub ON ub.id = o.buyer_id
            LEFT JOIN buyer_profiles bp ON bp.user_id = o.buyer_id
            LEFT JOIN users uf ON uf.id = o.farmer_id
            LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id
            WHERE o.status = \'cancelled\'';
    if ($where) {
        $sql .= ' AND ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY o.cancelled_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $farmerName = null;
    foreach ($farmers as $f) { if ((string) $f['id'] === $farmer) $farmerName = $f['label']; }
    $buyerName = null;
    foreach ($buyers as $b) { if ((string) $b['id'] === $buyer) $buyerName = $b['label']; }
    $filters[] = 'Cancelled by: ' . ($cRole !== '' ? ($cancelRoles[$cRole] ?? $cRole) : 'all');
    $filters[] = 'Stage: ' . ($stage !== '' ? $stage : 'all');
    $filters[] = 'Farmer: ' . ($farmerName ?? 'all');
    $filters[] = 'Buyer: ' . ($buyerName ?? 'all');
    if ($from !== '' && is_valid_date($from)) $filters[] = 'From: ' . $from;
    if ($to !== '' && is_valid_date($to)) $filters[] = 'To: ' . $to;
    $filters = array_values(array_filter($filters, fn ($f) => !str_ends_with($f, ': all')));
}

if (!isset($filters) || empty($filters)) {
    $filters = ['none'];
}


if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $pdf = build_report_pdf($reportTitle, $reportRef, $filters, $rows, $totals);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="animal-' . strtolower($reportRef) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$pdfUrl = 'admin_reports.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf']));
$pageTitle = 'Reports';
$activePage = 'reports';
require __DIR__ . '/partials/dashboard-layout.php';
?>
<style>
@media print {
    body.has-sidebar .sidebar,
    .sidebar-overlay,
    .sidebar-topbar,
    .dash-section-header .report-actions,
    .report-form,
    .report-tabs { display: none !important; }
    body.has-sidebar .sidebar-main { margin-left: 0 !important; padding: 0 !important; }
    .report-meta { display: block !important; }
    .report-table { border: none !important; }
    .dash-table tfoot { border-top: 2px solid #000 !important; }
}
@media screen {
    .report-meta { display: none; }
}
</style>

<div class="dash-section-header">
    <h1>Reports</h1>
    <p><?= e($reportRef) ?> — <?= e($reportTitle) ?></p>
    <div class="report-actions">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a class="btn btn-sm btn-primary" href="<?= e($pdfUrl) ?>">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

<ul class="nav nav-tabs report-tabs mb-4">
    <?php foreach ($reports as $key => $r): ?>
        <li class="nav-item">
            <a class="nav-link <?= $type === $key ? 'active' : '' ?>" href="admin_reports.php?t=<?= e($key) ?>">
                <?= e($r['title']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($type === 'registration'): ?>
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-2 mb-3 report-form">
        <input type="hidden" name="t" value="registration">
        <div><label class="form-label small mb-1" for="f-from">From date</label><input type="date" id="f-from" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-to">To date</label><input type="date" id="f-to" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-role">Role</label>
            <select name="role" id="f-role" class="form-select form-select-sm">
                <option value="">All roles</option>
                <?php foreach ($roles as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= (string) ($_GET['role'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Generate</button>
        <a href="admin_reports.php?t=registration" class="btn btn-sm btn-outline-primary">Reset</a>
    </form>
<?php elseif ($type === 'listings'): ?>
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-2 mb-3 report-form">
        <input type="hidden" name="t" value="listings">
        <div><label class="form-label small mb-1" for="f-from">Published from</label><input type="date" id="f-from" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-to">Published to</label><input type="date" id="f-to" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-cat">Category</label>
            <select name="category" id="f-cat" class="form-select form-select-sm">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (string) ($_GET['category'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-farmer">Farmer</label>
            <select name="farmer" id="f-farmer" class="form-select form-select-sm">
                <option value="">All farmers</option>
                <?php foreach ($farmers as $f): ?>
                    <option value="<?= (int) $f['id'] ?>" <?= (string) ($_GET['farmer'] ?? '') === (string) $f['id'] ? 'selected' : '' ?>><?= e($f['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-status">Status</label>
            <select name="status" id="f-status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach ($listingStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= (string) ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Generate</button>
        <a href="admin_reports.php?t=listings" class="btn btn-sm btn-outline-primary">Reset</a>
    </form>
<?php elseif ($type === 'orders'): ?>
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-2 mb-3 report-form">
        <input type="hidden" name="t" value="orders">
        <div><label class="form-label small mb-1" for="f-from">From date</label><input type="date" id="f-from" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-to">To date</label><input type="date" id="f-to" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-status">Status</label>
            <select name="status" id="f-status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach ($orderStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= (string) ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-farmer">Farmer</label>
            <select name="farmer" id="f-farmer" class="form-select form-select-sm">
                <option value="">All farmers</option>
                <?php foreach ($farmers as $f): ?>
                    <option value="<?= (int) $f['id'] ?>" <?= (string) ($_GET['farmer'] ?? '') === (string) $f['id'] ? 'selected' : '' ?>><?= e($f['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-buyer">Buyer</label>
            <select name="buyer" id="f-buyer" class="form-select form-select-sm">
                <option value="">All buyers</option>
                <?php foreach ($buyers as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (string) ($_GET['buyer'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>><?= e($b['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Generate</button>
        <a href="admin_reports.php?t=orders" class="btn btn-sm btn-outline-primary">Reset</a>
    </form>
<?php elseif ($type === 'verification'): ?>
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-2 mb-3 report-form">
        <input type="hidden" name="t" value="verification">
        <div><label class="form-label small mb-1" for="f-from">Submitted from</label><input type="date" id="f-from" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-to">Submitted to</label><input type="date" id="f-to" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-diff-from">Reviewed from</label><input type="date" id="f-diff-from" name="diff_from" value="<?= e($diffFrom) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-diff-to">Reviewed to</label><input type="date" id="f-diff-to" name="diff_to" value="<?= e($diffTo) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-vstatus">Review status</label>
            <select name="vstatus" id="f-vstatus" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach ($verifyStatuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= (string) ($_GET['vstatus'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-reviewer">Reviewer</label>
            <select name="reviewer" id="f-reviewer" class="form-select form-select-sm">
                <option value="">All reviewers</option>
                <?php foreach ($reviewers as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= (string) ($_GET['reviewer'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Generate</button>
        <a href="admin_reports.php?t=verification" class="btn btn-sm btn-outline-primary">Reset</a>
    </form>
<?php elseif ($type === 'fulfillment'): ?>
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-2 mb-3 report-form">
        <input type="hidden" name="t" value="fulfillment">
        <div><label class="form-label small mb-1" for="f-from">From date</label><input type="date" id="f-from" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-to">To date</label><input type="date" id="f-to" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-method">Method</label>
            <select name="method" id="f-method" class="form-select form-select-sm">
                <option value="">All methods</option>
                <?php foreach ($methods as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= (string) ($_GET['method'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-farmer">Farmer</label>
            <select name="farmer" id="f-farmer" class="form-select form-select-sm">
                <option value="">All farmers</option>
                <?php foreach ($farmers as $f): ?>
                    <option value="<?= (int) $f['id'] ?>" <?= (string) ($_GET['farmer'] ?? '') === (string) $f['id'] ? 'selected' : '' ?>><?= e($f['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-status">Order status</label>
            <select name="status" id="f-status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach ($orderStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= (string) ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Generate</button>
        <a href="admin_reports.php?t=fulfillment" class="btn btn-sm btn-outline-primary">Reset</a>
    </form>
<?php elseif ($type === 'cancellations'): ?>
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-2 mb-3 report-form">
        <input type="hidden" name="t" value="cancellations">
        <div><label class="form-label small mb-1" for="f-from">From date</label><input type="date" id="f-from" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-to">To date</label><input type="date" id="f-to" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-1" for="f-crole">Cancelled by</label>
            <select name="crole" id="f-crole" class="form-select form-select-sm">
                <option value="">All roles</option>
                <?php foreach ($cancelRoles as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= (string) ($_GET['crole'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-stage">Stage at cancellation</label>
            <select name="stage" id="f-stage" class="form-select form-select-sm">
                <option value="">All stages</option>
                <?php foreach ($stages as $s): ?>
                    <option value="<?= e($s) ?>" <?= (string) ($_GET['stage'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-farmer">Farmer</label>
            <select name="farmer" id="f-farmer" class="form-select form-select-sm">
                <option value="">All farmers</option>
                <?php foreach ($farmers as $f): ?>
                    <option value="<?= (int) $f['id'] ?>" <?= (string) ($_GET['farmer'] ?? '') === (string) $f['id'] ? 'selected' : '' ?>><?= e($f['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label small mb-1" for="f-buyer">Buyer</label>
            <select name="buyer" id="f-buyer" class="form-select form-select-sm">
                <option value="">All buyers</option>
                <?php foreach ($buyers as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (string) ($_GET['buyer'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>><?= e($b['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Generate</button>
        <a href="admin_reports.php?t=cancellations" class="btn btn-sm btn-outline-primary">Reset</a>
    </form>
<?php endif; ?>

<p class="text-muted small report-meta">
    Generated: <?= e(date('Y-m-d H:i')) ?> &middot; Filters: <?= e(implode(', ', $filters)) ?> &middot;
    <?= count($rows) ?> <?= $type === 'fulfillment' || $type === 'cancellations' ? 'record(s)' : 'row(s)' ?>
</p>

<div class="table-responsive">
    <table class="dash-table align-middle report-table">
        <?php if ($type === 'registration'): ?>
        <thead><tr>
            <th class="text-nowrap col-when">Registered</th><th class="col-email">Email</th><th class="col-role">Role</th><th class="col-status">Status</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td class="cell-muted text-nowrap"><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><span class="dash-badge <?= $r['role'] === 'farmer' ? 'dash-badge--role-farmer' : ($r['role'] === 'admin' ? 'dash-badge--role-admin' : 'dash-badge--role-buyer') ?>"><?= e(ucfirst($r['role'])) ?></span></td>
                <td class="cell-muted"><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif ($type === 'listings'): ?>
        <thead><tr>
            <th>Listing</th><th>Category</th><th>Farmer</th><th>Unit</th><th class="text-end">Price</th>
            <th class="text-end">Available</th><th>Status</th><th class="text-nowrap">Published</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td>#<?= (int) $r['id'] ?> <?= e($r['name']) ?></td>
                <td class="cell-muted"><?= e($r['category']) ?></td>
                <td class="cell-muted"><?= e($r['farmer']) ?></td>
                <td class="cell-muted"><?= e($r['unit_symbol'] ?? '') ?></td>
                <td class="text-end"><?= p($r['price_per_unit']) ?></td>
                <td class="text-end"><?= e(fmt_qty($r['available_quantity'])) ?></td>
                <td><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></td>
                <td class="cell-muted text-nowrap"><?= $r['published_at'] ? e(date('Y-m-d', strtotime($r['published_at']))) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif ($type === 'orders'): ?>
        <thead><tr>
            <th>Order</th><th class="text-nowrap">Date</th><th>Buyer</th><th>Farmer</th><th>Listing</th>
            <th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Produce</th>
            <th class="text-end">Delivery</th><th class="text-end">Total</th><th>Method</th><th>Status</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['order_number']) ?></td>
                <td class="cell-muted text-nowrap"><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                <td><?= e($r['buyer']) ?></td>
                <td class="cell-muted"><?= e($r['farmer']) ?></td>
                <td class="cell-muted"><?= e($r['listing']) ?></td>
                <td class="text-end"><?= e(fmt_qty($r['quantity'])) ?> <?= e($r['unit_symbol'] ?? '') ?></td>
                <td class="text-end"><?= p($r['unit_price']) ?></td>
                <td class="text-end"><?= p($r['produce_subtotal']) ?></td>
                <td class="text-end"><?= p($r['delivery_fee']) ?></td>
                <td class="text-end fw-medium"><?= p($r['produce_subtotal'] + $r['delivery_fee']) ?></td>
                <td class="cell-muted"><?= $r['delivery_method'] === 'farmer_delivery' ? 'Delivery' : 'Pickup' ?></td>
                <td><span class="dash-badge dash-badge--secondary"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="12" class="cell-muted"><?= count($rows) ?> order(s)</td>
            </tr>
        </tfoot>

        <?php elseif ($type === 'verification'): ?>
        <thead><tr>
            <th>Farm</th><th>Contact</th><th>Location</th><th class="text-nowrap">Submitted</th>
            <th>Valid ID</th><th>Status</th><th class="text-nowrap">Reviewed</th><th>Reviewer</th><th>Reason</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['farm_name']) ?></td>
                <td class="cell-muted"><?= e($r['contact_person']) ?></td>
                <td class="cell-muted"><?= e($r['municipality_city'] . ', ' . $r['province']) ?></td>
                <td class="cell-muted text-nowrap"><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                <td class="cell-muted"><?= e(ucwords(str_replace('_', ' ', $r['valid_id_type']))) ?></td>
                <td>
                    <?php if ($r['verification_status'] === 'approved'): ?>
                        <span class="dash-badge dash-badge--success">Approved</span>
                    <?php elseif ($r['verification_status'] === 'rejected'): ?>
                        <span class="dash-badge dash-badge--danger">Rejected</span>
                    <?php else: ?>
                        <span class="dash-badge dash-badge--secondary">Pending</span>
                    <?php endif; ?>
                </td>
                <td class="cell-muted text-nowrap"><?= $r['verified_at'] ? e(date('Y-m-d', strtotime($r['verified_at']))) : '—' ?></td>
                <td class="cell-muted"><?= e($r['reviewer_email'] ?? '—') ?></td>
                <td class="cell-muted"><?= e($r['rejection_reason'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif ($type === 'fulfillment'): ?>
        <thead><tr>
            <th>Order</th><th class="text-nowrap">Date</th><th>Farmer</th><th>Listing</th>
            <th class="text-end">Produce</th><th class="text-end">Delivery</th><th class="text-end">Total</th>
            <th>Method</th><th>Status</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['order_number']) ?></td>
                <td class="cell-muted text-nowrap"><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                <td class="cell-muted"><?= e($r['farmer']) ?></td>
                <td class="cell-muted"><?= e($r['listing']) ?> <span class="text-muted">(<?= e(fmt_qty($r['quantity'])) ?> <?= e($r['unit_symbol'] ?? '') ?>)</span></td>
                <td class="text-end"><?= p($r['produce_subtotal']) ?></td>
                <td class="text-end"><?= p($r['delivery_fee']) ?></td>
                <td class="text-end fw-medium"><?= p($r['final_total']) ?></td>
                <td class="cell-muted"><?= $r['delivery_method'] === 'farmer_delivery' ? 'Delivery' : 'Pickup' ?></td>
                <td><span class="dash-badge dash-badge--secondary"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if ($totals): ?>
        <tfoot>
            <tr>
                <td colspan="4">Totals (<?= (int) $totals['count'] ?> orders)</td>
                <td class="text-end"><?= p($totals['subtotal']) ?></td>
                <td class="text-end"><?= p($totals['fees']) ?></td>
                <td class="text-end fw-bold"><?= p($totals['final']) ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        <?php endif; ?>

        <?php elseif ($type === 'cancellations'): ?>
        <thead><tr>
            <th>Order</th><th class="text-nowrap">Cancelled</th><th>By</th><th>Stage</th>
            <th>Farmer</th><th>Buyer</th><th>Reason</th>
        </tr></thead>
        <tbody>
            <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center cell-muted">No cancellations match the filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['order_number']) ?></td>
                <td class="cell-muted text-nowrap"><?= $r['cancelled_at'] ? e(date('Y-m-d', strtotime($r['cancelled_at']))) : '—' ?></td>
                <td class="cell-muted"><?= e(ucfirst($r['cancelled_by_role'] ?? '—')) ?></td>
                <td class="cell-muted"><?= e(ucfirst(str_replace('_', ' ', $r['stage'] ?? ''))) ?></td>
                <td class="cell-muted"><?= e($r['farmer']) ?></td>
                <td class="cell-muted"><?= e($r['buyer']) ?></td>
                <td class="cell-muted"><?= e($r['cancellation_reason'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php endif; ?>
    </table>
</div>

<?php if ($type !== 'cancellations' && !$rows): ?>
<div class="empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon">
        <path d="M9.293 1.293a1 1 0 0 1 .707-.293.91.91 0 0 1 .354.086.91.91 0 0 1 .244.208.91.91 0 0 1 .17.341.91.91 0 0 1 .024.365A3.99 3.99 0 0 1 13.5 4a4 4 0 0 1-3.293 3.949 4 4 0 0 1-4.5-1.7 1 1 0 1 0-1.7 1.052A6 6 0 0 0 8 9.886V12H6a1 1 0 0 0-1 1v1.5a.5.5 0 1 0 1 0V13h4v1.5a.5.5 0 1 0 1 0V13a1 1 0 0 0-1-1h-2v-2.114a6.01 6.01 0 0 0 4.77-4.604A4 4 0 0 0 15 4a5 5 0 0 0-.586-2.414.5.5 0 0 1 .301-.757A3.98 3.98 0 0 0 15.5 3 4 4 0 0 0 17.5 6" />
    </svg>
    <h2 class="empty-state-title">No rows for the current filters</h2>
    <p class="empty-state-description">Adjust the filters to broaden the report.</p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
