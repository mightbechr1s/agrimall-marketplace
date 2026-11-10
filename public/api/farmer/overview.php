<?php
require __DIR__ . '/../_bootstrap.php';

$user = api_require_auth();
if (!in_array($user['role'], ['farmer', 'admin', 'manager'], true)) {
    api_error('Farmer access only.', 403);
}

$pdo = DB::pdo();
$farmerId = (int) $user['id'];

$count = function (string $sql) use ($pdo, $farmerId): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['fid' => $farmerId]);
    return (int) $stmt->fetchColumn();
};

$pending = $count("SELECT COUNT(*) FROM orders WHERE farmer_id = :fid AND status IN ('pending','delivery_fee_review')");
$active = $count("SELECT COUNT(*) FROM orders WHERE farmer_id = :fid AND status IN ('confirmed','preparing','ready_for_pickup','out_for_delivery')");
$completed = $count("SELECT COUNT(*) FROM orders WHERE farmer_id = :fid AND status = 'completed'");
$listings = $count("SELECT COUNT(*) FROM produce_listings WHERE farmer_id = :fid AND status = 'active'");
$soldOut = $count("SELECT COUNT(*) FROM produce_listings WHERE farmer_id = :fid AND status = 'sold_out'");

$revenueStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(unit_price * quantity + delivery_fee), 0)
     FROM orders
     WHERE farmer_id = :fid AND status = 'completed'
       AND completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);
$revenueStmt->execute(['fid' => $farmerId]);
$revenue = (float) $revenueStmt->fetchColumn();

$profileStmt = $pdo->prepare('SELECT farm_name, verification_status FROM farmer_profiles WHERE user_id = :fid');
$profileStmt->execute(['fid' => $farmerId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: ['farm_name' => '', 'verification_status' => 'pending'];

$recentStmt = $pdo->prepare(
    "SELECT id, order_number, status, created_at, listing_name, quantity, unit_symbol, delivery_method,
            unit_price, delivery_fee
     FROM v_order_summary
     WHERE farmer_id = :fid
     ORDER BY created_at DESC
     LIMIT 5"
);
$recentStmt->execute(['fid' => $farmerId]);
$recent = array_map(function ($o) {
    return [
        'id' => (int) $o['id'],
        'order_number' => $o['order_number'],
        'status' => $o['status'],
        'created_at' => $o['created_at'],
        'listing_name' => $o['listing_name'],
        'quantity' => (float) $o['quantity'],
        'unit' => $o['unit_symbol'],
        'total' => (float) $o['unit_price'] * (float) $o['quantity'] + (float) $o['delivery_fee'],
        'delivery_method' => $o['delivery_method'],
    ];
}, $recentStmt->fetchAll(PDO::FETCH_ASSOC));

api_ok([
    'farm_name' => $profile['farm_name'],
    'verification_status' => $profile['verification_status'],
    'stats' => [
        'pending_orders' => $pending,
        'active_orders' => $active,
        'completed_orders' => $completed,
        'active_listings' => $listings,
        'sold_out_listings' => $soldOut,
        'revenue_this_month' => $revenue,
    ],
    'recent_orders' => $recent,
]);
