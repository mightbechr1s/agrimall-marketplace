<?php
require __DIR__ . '/_bootstrap.php';

$pdo = DB::pdo();
$user = api_require_auth();
$role = $user['role'];
$userId = $user['id'];

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$column = in_array($role, ['farmer', 'admin', 'manager'], true) && $role !== 'buyer' ? 'farmer_id' : 'buyer_id';
// For buyers, filter by buyer_id. For farmers/admins, filter by farmer_id (their orders as seller).
if ($role === 'buyer') {
    $column = 'buyer_id';
} else {
    $column = 'farmer_id';
}

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

$os = $pdo->prepare("SELECT id, order_number, status, created_at, listing_id, listing_name, quantity, unit_price, unit_symbol, delivery_method, delivery_fee, farm_name FROM v_order_summary $where ORDER BY created_at DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) {
    $os->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$os->bindValue(':lim', $perPage, PDO::PARAM_INT);
$os->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$os->execute();
$orders = $os->fetchAll(PDO::FETCH_ASSOC);

$formatted = array_map(function ($o) {
    return [
        'id' => (int) $o['id'],
        'order_number' => $o['order_number'],
        'status' => $o['status'],
        'created_at' => $o['created_at'],
        'listing_id' => (int) $o['listing_id'],
        'listing_name' => $o['listing_name'],
        'quantity' => (float) $o['quantity'],
        'unit_price' => (float) $o['unit_price'],
        'unit' => $o['unit_symbol'],
        'total' => (float) $o['unit_price'] * (float) $o['quantity'] + (float) $o['delivery_fee'],
        'delivery_method' => $o['delivery_method'],
        'delivery_fee' => (float) $o['delivery_fee'],
        'farm_name' => $o['farm_name'],
    ];
}, $orders);

api_ok(['total' => $total, 'page' => $page, 'total_pages' => $pages, 'orders' => $formatted]);
