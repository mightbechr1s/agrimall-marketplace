<?php
require __DIR__ . '/_bootstrap.php';

$pdo = DB::pdo();
$user = api_require_auth();
$role = $user['role'];
$userId = $user['id'];

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where = '';
$params = [];
if ($role === 'buyer') {
    $where = 'WHERE buyer_id = :id';
    $params = ['id' => $userId];
} elseif ($role === 'farmer') {
    $where = 'WHERE farmer_id = :id';
    $params = ['id' => $userId];
}
// Admin/manager see every order; $where stays empty.
if ($statusFilter !== '') {
    $where .= ($where === '' ? 'WHERE' : ' AND') . ' status = :status';
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
