<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$pdo = DB::pdo();

// Admin listing deactivation (POST handler, before any output).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate_listing') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
    $listingId = (int) ($_POST['listing_id'] ?? 0);
    $reason    = trim((string) ($_POST['reason'] ?? ''));
    $returnTo  = 'admin_reports.php?t=listings';
    if (!$listingId || $reason === '') {
        flash_set('error', 'Listing ID and a reason are required.');
        redirect_to($returnTo);
    }
    if (mb_strlen($reason) > FIELD_LIMITS['reason']) {
        flash_set('error', 'Reason is too long (max ' . FIELD_LIMITS['reason'] . ' characters).');
        redirect_to($returnTo);
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM produce_listings WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $listingId]);
        $listing = $stmt->fetch();
        if (!$listing) {
            throw new UserFacingException('Listing not found.');
        }
        if ($listing['status'] === 'admin_deactivated') {
            throw new UserFacingException('This listing is already deactivated.');
        }
        if ($listing['status'] === 'archived') {
            throw new UserFacingException('Archived listings cannot be deactivated.');
        }
        $pdo->prepare("UPDATE produce_listings SET status = 'admin_deactivated' WHERE id = :id")
            ->execute(['id' => $listingId]);
        $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, reason, created_at)
             VALUES (:admin, :action, :type, :id, :reason, NOW())'
        )->execute([
            'admin'  => current_user_id(),
            'action' => 'deactivate_listing',
            'type'   => 'listing',
            'id'     => $listingId,
            'reason' => $reason,
        ]);
        $pdo->commit();
        flash_set('success', 'Listing deactivated.');
    } catch (UserFacingException $e) {
        $pdo->rollBack();
        flash_set('error', $e->getMessage());
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash_set('error', generic_error_message(app_log_exception($e, 'admin_listing_action')));
    }
    redirect_to($returnTo);
}

/** Cap on PDF export rows. Beyond this the file (and PHP memory) grows with
 *  the dataset, so a flooded admin URL could exhaust the server. */
const REPORT_EXPORT_MAX_ROWS = 2000;

/** Cap on farmer/buyer filter dropdowns. A <select> gets one <option> per
 *  account, so an uncapped list is unusable and ships the whole table in HTML. */
const REPORT_FILTER_MAX_OPTIONS = 100;

// Report whitelist (FR-REP-001,002,003,007,008,009; FR-REP-004 is the farmer-side summary).
$reports = [
    'registration'   => ['title' => 'Registration Report',    'ref' => 'FR-REP-001'],
    'listings'       => ['title' => 'Listing Report',         'ref' => 'FR-REP-002'],
    'orders'         => ['title' => 'Order Report',           'ref' => 'FR-REP-003'],
    'verification'   => ['title' => 'Farmer Verification Report', 'ref' => 'FR-REP-007'],
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
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

// Dropdown source data; loaded even when the result set is empty.
$roles = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Admin'];
$categories = $pdo->query('SELECT id, name FROM produce_categories ORDER BY name')->fetchAll();
$listingStatuses = ['draft', 'active', 'paused', 'sold_out', 'archived', 'admin_deactivated'];
$orderStatuses = ['pending', 'delivery_fee_review', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'rejected', 'cancelled'];
$verifyStatuses = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$methods = ['pickup' => 'Pickup', 'farmer_delivery' => 'Farmer Delivery'];
$cancelRoles = ['buyer' => 'Buyer', 'farmer' => 'Farmer', 'admin' => 'Admin'];
$farmers = $pdo->prepare(
    'SELECT u.id, COALESCE(fp.farm_name, u.email) AS label
     FROM users u LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
     WHERE u.role = \'farmer\' ORDER BY label
     LIMIT ' . REPORT_FILTER_MAX_OPTIONS
);
$farmers->execute();
$farmers = $farmers->fetchAll();
$buyers = $pdo->prepare(
    'SELECT u.id, COALESCE(bp.full_name, u.email) AS label
     FROM users u LEFT JOIN buyer_profiles bp ON bp.user_id = u.id
     WHERE u.role = \'buyer\' ORDER BY label
     LIMIT ' . REPORT_FILTER_MAX_OPTIONS
);
$buyers->execute();
$buyers = $buyers->fetchAll();

/**
 * Label for a selected farmer/buyer, resolved with one indexed lookup.
 *
 * The dropdown lists above are capped, so scanning them would miss anyone past
 * the cap; and the previous version looped the entire user table per report.
 */
function report_entity_label(PDO $pdo, string $role, string $id): ?string
{
    if ($id === '' || !ctype_digit($id)) {
        return null;
    }
    $profileTable = $role === 'farmer' ? 'farmer_profiles' : 'buyer_profiles';
    $nameColumn   = $role === 'farmer' ? 'farm_name' : 'full_name';
    // $role is never user input here; it is one of two literals from this file.
    if (!in_array($role, ['farmer', 'buyer'], true)) {
        return null;
    }
    $sql = "SELECT COALESCE(p.$nameColumn, u.email)
            FROM users u LEFT JOIN $profileTable p ON p.user_id = u.id
            WHERE u.id = :id AND u.role = :role";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => (int) $id, 'role' => $role]);
    $label = $stmt->fetchColumn();
    return $label === false || $label === null ? null : (string) $label;
}
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
    $baseSql = 'SELECT id, email, role, status, created_at FROM users';
    if ($where) {
        $baseSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $baseSql .= ' ORDER BY created_at DESC';

    // Total count for pagination
    $countSql = 'SELECT COUNT(*) FROM users';
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = $baseSql . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
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
    $baseSql = 'SELECT l.id, l.name, l.price_per_unit, l.available_quantity, l.status, l.published_at,
                       c.name AS category, u.symbol AS unit_symbol, COALESCE(fp.farm_name, ue.email) AS farmer
                FROM produce_listings l
                JOIN produce_categories c ON c.id = l.category_id
                LEFT JOIN quantity_units u ON u.id = l.unit_id
                LEFT JOIN users ue ON ue.id = l.farmer_id
                LEFT JOIN farmer_profiles fp ON fp.user_id = l.farmer_id';
    if ($where) {
        $baseSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $baseSql .= ' ORDER BY l.created_at DESC';

    // Total count for pagination
    $countSql = 'SELECT COUNT(*) FROM produce_listings l
                 JOIN produce_categories c ON c.id = l.category_id
                 LEFT JOIN quantity_units u ON u.id = l.unit_id
                 LEFT JOIN users ue ON ue.id = l.farmer_id
                 LEFT JOIN farmer_profiles fp ON fp.user_id = l.farmer_id';
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = $baseSql . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $catName = null;
    foreach ($categories as $c) { if ((string) $c['id'] === $category) $catName = $c['name']; }
    $farmerName = report_entity_label($pdo, 'farmer', (string) $farmer);
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
    // Only the columns the report renders or exports; the rest of o.*
    // (addresses, notes, reasons text) was dead weight per row.
    $baseSql = 'SELECT o.id, o.order_number, o.quantity, o.unit_price, o.delivery_fee,
                       o.delivery_method, o.status, o.created_at,
                       o.cancelled_at, o.cancelled_by_role, o.cancellation_reason,
                       pl.name AS listing, pl.unit_id,
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
        $baseSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $baseSql .= ' ORDER BY o.created_at DESC';

    // Total count for pagination
    $countSql = 'SELECT COUNT(*) FROM orders o
                 JOIN produce_listings pl ON pl.id = o.listing_id
                 LEFT JOIN quantity_units u ON u.id = pl.unit_id
                 LEFT JOIN users ub ON ub.id = o.buyer_id
                 LEFT JOIN buyer_profiles bp ON bp.user_id = o.buyer_id
                 LEFT JOIN users uf ON uf.id = o.farmer_id
                 LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id';
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = $baseSql . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $farmerName = report_entity_label($pdo, 'farmer', (string) $farmer);
    $buyerName = report_entity_label($pdo, 'buyer', (string) $buyer);
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
    // Only the columns the report renders or exports (fp.* pulled every TEXT
    // column — address, description, lat/lng, id image, reasons — into memory).
    $baseSql = 'SELECT fp.user_id, fp.farm_name, fp.barangay, fp.municipality_city, fp.province,
                       fp.contact_person, fp.contact_phone, fp.contact_email,
                       fp.valid_id_type, fp.verification_status, fp.verified_by,
                       fp.verified_at, fp.rejection_reason, fp.created_at,
                       u.email, ue.email AS reviewer_email
                FROM farmer_profiles fp
                JOIN users u ON u.id = fp.user_id
                LEFT JOIN users ue ON ue.id = fp.verified_by';
    if ($where) {
        $baseSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $baseSql .= ' ORDER BY fp.created_at DESC';

    // Total count for pagination
    $countSql = 'SELECT COUNT(*) FROM farmer_profiles fp
                 JOIN users u ON u.id = fp.user_id
                 LEFT JOIN users ue ON ue.id = fp.verified_by';
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = $baseSql . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
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
    $baseSql = 'SELECT o.order_number, o.created_at, o.quantity, o.unit_price, o.delivery_fee, o.status, o.delivery_method,
                       pl.name AS listing, COALESCE(fp.farm_name, uf.email) AS farmer, u.symbol AS unit_symbol,
                       (o.quantity * o.unit_price) AS produce_subtotal,
                       (o.quantity * o.unit_price + o.delivery_fee) AS final_total
                FROM orders o
                JOIN produce_listings pl ON pl.id = o.listing_id
                LEFT JOIN quantity_units u ON u.id = pl.unit_id
                LEFT JOIN users uf ON uf.id = o.farmer_id
                LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id';
    if ($where) {
        $baseSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $baseSql .= ' ORDER BY o.created_at DESC';

    // Total count for pagination
    $countSql = 'SELECT COUNT(*) FROM orders o
                 JOIN produce_listings pl ON pl.id = o.listing_id
                 LEFT JOIN quantity_units u ON u.id = pl.unit_id
                 LEFT JOIN users uf ON uf.id = o.farmer_id
                 LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id';
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = $baseSql . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();
    // Totals cover the whole filtered set, not just the page on screen.
    $totalsStmt = $pdo->prepare('SELECT COUNT(*) AS count,
                COALESCE(SUM(o.quantity * o.unit_price), 0) AS subtotal,
                COALESCE(SUM(o.delivery_fee), 0) AS fees,
                COALESCE(SUM(o.quantity * o.unit_price + o.delivery_fee), 0) AS final
            FROM orders o
            JOIN produce_listings pl ON pl.id = o.listing_id
            LEFT JOIN quantity_units u ON u.id = pl.unit_id
            LEFT JOIN users uf ON uf.id = o.farmer_id
            LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
    $totalsStmt->execute($params);
    $totals = $totalsStmt->fetch();
    $farmerName = report_entity_label($pdo, 'farmer', (string) $farmer);
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
    // cancelled→reopened→cancelled orders have TWO history rows; older rows would
    // duplicate the report line. Keep only each order's latest cancel event.
    $baseSql = 'SELECT o.order_number, o.cancelled_at, o.cancelled_by_role, o.cancellation_reason,
                       COALESCE(bp.full_name, ub.email) AS buyer, COALESCE(fp.farm_name, uf.email) AS farmer,
                       osh.from_status AS stage
                FROM orders o
                LEFT JOIN order_status_history osh
                    ON osh.id = (SELECT h.id FROM order_status_history h
                                 WHERE h.order_id = o.id AND h.to_status = \'cancelled\'
                                 ORDER BY h.created_at DESC LIMIT 1)
                LEFT JOIN users ub ON ub.id = o.buyer_id
                LEFT JOIN buyer_profiles bp ON bp.user_id = o.buyer_id
                LEFT JOIN users uf ON uf.id = o.farmer_id
                LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id
                WHERE o.status = \'cancelled\'';
    if ($where) {
        $baseSql .= ' AND ' . implode(' AND ', $where);
    }
    $baseSql .= ' ORDER BY o.cancelled_at DESC';

    // Total count for pagination
    $countSql = 'SELECT COUNT(*) FROM orders o
                 LEFT JOIN order_status_history osh ON osh.order_id = o.id AND osh.to_status = \'cancelled\'
                 LEFT JOIN users ub ON ub.id = o.buyer_id
                 LEFT JOIN buyer_profiles bp ON bp.user_id = o.buyer_id
                 LEFT JOIN users uf ON uf.id = o.farmer_id
                 LEFT JOIN farmer_profiles fp ON fp.user_id = o.farmer_id
                 WHERE o.status = \'cancelled\'';
    if ($where) {
        $countSql .= ' AND ' . implode(' AND ', $where);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = $baseSql . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $farmerName = report_entity_label($pdo, 'farmer', (string) $farmer);
    $buyerName = report_entity_label($pdo, 'buyer', (string) $buyer);
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


if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportRows = $rows;
    if (isset($baseSql)) {
        $exportStmt = $pdo->prepare($baseSql . ' LIMIT ' . REPORT_EXPORT_MAX_ROWS);
        $exportStmt->execute($params);
        $exportRows = $exportStmt->fetchAll();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="animal-' . strtolower($reportRef) . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($exportRows)) {
        fputcsv($out, array_keys($exportRows[0]));
        foreach ($exportRows as $r) {
            fputcsv($out, array_values($r));
        }
    }
    fclose($out);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Export the filtered set, but cap it. Fetching every matching row makes
    // both PHP memory and the rendered PDF grow with the dataset, which turns
    // this admin URL into a denial-of-service lever. The cap is disclosed in
    // the PDF rather than silently truncating the file.
    $exportRows = $rows;
    $exportNote = '';
    if (isset($baseSql)) {
        $exportStmt = $pdo->prepare($baseSql . ' LIMIT ' . REPORT_EXPORT_MAX_ROWS);
        $exportStmt->execute($params);
        $exportRows = $exportStmt->fetchAll();
        if (count($exportRows) >= REPORT_EXPORT_MAX_ROWS) {
            $exportNote = 'Showing the first ' . REPORT_EXPORT_MAX_ROWS
                . ' matching rows. Narrow the filters to export a complete set.';
        }
    }
    $pdf = build_report_pdf($reportTitle, $reportRef, $filters, $exportRows, $totals, $exportNote);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="animal-' . strtolower($reportRef) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$pdfUrl = 'admin_reports.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf']));
$csvUrl = 'admin_reports.php?' . http_build_query(array_merge($_GET, ['export' => 'csv']));
$pageTitle = 'Reports';
$activePage = 'reports';
$role = 'admin';
require __DIR__ . '/partials/dashboard-layout.php';
?>

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
        <a class="btn btn-sm btn-success" href="<?= e($csvUrl) ?>">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
    </div>
</div>

<form method="get" action="admin_reports.php" class="d-flex align-items-center gap-3 mb-4">
    <label for="report-type" class="form-label small mb-0">Report</label>
    <select id="report-type" name="t" class="form-select form-select-sm" style="max-width:260px;" onchange="this.form.submit()">
        <?php foreach ($reports as $key => $r): ?>
            <option value="<?= e($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($r['title']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($type === 'registration'): ?>
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-3 mb-3 report-form">
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
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-3 mb-3 report-form">
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
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-3 mb-3 report-form">
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
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-3 mb-3 report-form">
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
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-3 mb-3 report-form">
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
    <form method="get" action="admin_reports.php" class="d-flex flex-wrap align-items-end gap-3 mb-3 report-form">
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
    <?= count($rows) ?><?= $total > count($rows) ? ' of ' . $total : '' ?> <?= $type === 'fulfillment' || $type === 'cancellations' ? 'record(s)' : 'row(s)' ?>
</p>

<?php if ($type === 'listings' && !empty($rows)): ?>
<form method="post" action="admin_listing_action.php" id="bulk-form" class="d-flex align-items-center gap-3 mb-3" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="deactivate_listing">
    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="var ids=[]; document.querySelectorAll('.listing-cb:checked').forEach(function(cb){ids.push(cb.value);}); if(!ids.length){alert('Select at least one listing.');return false;} var f=document.getElementById('bulk-form'); f.querySelectorAll('input[name=\'listing_ids[]\']').forEach(function(e){e.remove();}); ids.forEach(function(id){var i=document.createElement('input');i.type='hidden';i.name='listing_ids[]';i.value=id;f.appendChild(i);}); return confirm('Mark selected listings as out of stock?');">
        <i class="bi bi-x-circle me-1"></i>Mark selected out of stock
    </button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function(){
  function toggleBulk(){
    var any = document.querySelectorAll('.listing-cb:checked').length > 0;
    var f = document.getElementById('bulk-form');
    if (f) f.style.setProperty('display', any ? 'flex' : 'none', 'important');
  }
  document.addEventListener('change', function(e){ if (e.target.classList && e.target.classList.contains('listing-cb')) toggleBulk(); });
  var sa = document.getElementById('selectAllListings');
  if (sa) sa.addEventListener('change', function(){ document.querySelectorAll('.listing-cb').forEach(function(cb){ cb.checked = sa.checked; }); toggleBulk(); });
  toggleBulk();
});
</script>
<?php endif; ?>
<div class="table-responsive">
    <table class="dash-table align-middle report-table">
        <?php if ($type === 'registration'): ?>
        <thead><tr>
            <th class="text-nowrap col-when">Registered</th><th class="col-email">Email</th><th class="col-role">Role</th><th class="col-status">Status</th><th>Action</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td class="cell-muted text-nowrap"><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><span class="dash-badge <?= $r['role'] === 'farmer' ? 'dash-badge--role-farmer' : ($r['role'] === 'admin' ? 'dash-badge--role-admin' : 'dash-badge--role-buyer') ?>"><?= e(ucfirst($r['role'])) ?></span></td>
                <td><?= status_badge($r['status']) ?></td>
                <td>
                    <?php if ((int) $r['id'] !== (int) current_user_id()): ?>
                      <?php if ($r['status'] === 'active'): ?>
                        <form method="post" action="admin_user_action.php" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="action" value="suspend">
                            <input type="text" name="reason" class="form-control form-control-sm d-inline-block" style="width:160px;" placeholder="Reason (required)" required maxlength="500">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Suspend this account?');">Suspend</button>
                        </form>
                      <?php else: ?>
                        <form method="post" action="admin_user_action.php" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="action" value="activate">
                            <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                        </form>
                      <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted small">You</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif ($type === 'listings'): ?>
        <thead><tr>
            <th><input type="checkbox" id="selectAllListings" aria-label="Select all"></th>
            <th>Listing</th><th>Category</th><th>Farmer</th><th>Unit</th><th class="text-end">Price</th>
            <th class="text-end">Available</th><th>Status</th><th class="text-nowrap">Published</th><th>Action</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><input type="checkbox" name="listing_ids[]" value="<?= (int) $r['id'] ?>" class="listing-cb"></td>
                <td><?= (int) $r['id'] ?> <?= e($r['name']) ?></td>
                <td class="cell-muted"><?= e($r['category']) ?></td>
                <td class="cell-muted"><?= e($r['farmer']) ?></td>
                <td class="cell-muted"><?= e($r['unit_symbol'] ?? '') ?></td>
                <td class="text-end"><?= p($r['price_per_unit']) ?></td>
                <td class="text-end"><?= e(fmt_qty($r['available_quantity'])) ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td class="cell-muted text-nowrap"><?= $r['published_at'] ? e(date('Y-m-d', strtotime($r['published_at']))) : '—' ?></td>
                  <td>
                      <?php if ($r['status'] !== 'sold_out' && $r['status'] !== 'archived'): ?>
                      <form method="post" action="admin_listing_action.php" class="d-inline" onsubmit="return confirm('Mark this listing as out of stock?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="deactivate_listing">
                          <input type="hidden" name="listing_id" value="<?= (int) $r['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Out of stock</button>
                      </form>
                      <?php elseif ($r['status'] === 'sold_out'): ?>
                      <span class="text-muted small">Out of stock</span>
                      <?php endif; ?>
                  </td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif ($type === 'orders'): ?>
        <thead><tr>
            <th>Order</th><th class="text-nowrap">Date</th><th>Buyer</th><th>Farmer</th><th>Listing</th>
            <th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Produce</th>
            <th class="text-end">Delivery</th><th class="text-end">Total</th><th>Method</th><th>Status</th>
            <th>Cancelled at</th><th>Cancelled by</th><th>Cancel reason</th>
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
                <td><?= status_badge($r['status']) ?></td>
                <td class="cell-muted text-nowrap"><?= !empty($r['cancelled_at']) ? e(date('Y-m-d', strtotime($r['cancelled_at']))) : '—' ?></td>
                <td class="cell-muted"><?= e($r['cancelled_by_role'] ?? '—') ?></td>
                <td class="cell-muted"><?= e($r['cancellation_reason'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="12" class="cell-muted"><?= count($rows) ?><?= $total > count($rows) ? ' of ' . $total : '' ?> order(s)</td>
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
                <td><?= status_badge($r['verification_status']) ?></td>
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
                <td><?= status_badge($r['status']) ?></td>
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

<?php if ($totalPages > 1): ?>
    <?php
    $paramParts = ['t=' . urlencode($type)];
    // Preserve all filter parameters
    foreach ($_GET as $key => $value) {
        if ($key !== 'page' && $key !== 't' && $key !== 'export') {
            $paramParts[] = $key . '=' . urlencode((string)$value);
        }
    }
    $param = implode('&', $paramParts);
    include __DIR__ . '/partials/pager.php';
    ?>
<?php endif; ?>

<?php if ($type === 'listings'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var selectAll = document.getElementById('selectAllListings');
    if (!selectAll) return;
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.listing-cb').forEach(function (cb) {
            cb.checked = selectAll.checked;
        });
    });
});
</script>
<?php endif; ?>

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