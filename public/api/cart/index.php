<?php
require __DIR__ . '/../_bootstrap.php';
$pdo = DB::pdo();
$user = api_require_auth();

$stmt = $pdo->prepare(
    'SELECT ci.id AS cart_id, ci.quantity, pl.id AS listing_id, pl.name, pl.price_per_unit,
            pl.minimum_order_qty, pl.maximum_order_qty, pl.available_quantity,
            fp.farm_name, qu.symbol,
            (SELECT li.image_path FROM listing_images li WHERE li.listing_id = pl.id AND li.is_primary = 1 ORDER BY li.sort_order LIMIT 1) AS primary_image
     FROM cart_items ci
     JOIN produce_listings pl ON ci.listing_id = pl.id
     JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     WHERE ci.buyer_id = :buyer
     ORDER BY ci.created_at'
);
$stmt->execute(['buyer' => $user['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($items as &$item) {
    $item['subtotal'] = (float) $item['price_per_unit'] * (float) $item['quantity'];
    $total += $item['subtotal'];
    $item['cart_id'] = (int) $item['cart_id'];
    $item['listing_id'] = (int) $item['listing_id'];
    $item['quantity'] = (float) $item['quantity'];
    $item['price_per_unit'] = (float) $item['price_per_unit'];
    $item['minimum_order_qty'] = (float) $item['minimum_order_qty'];
    $item['available_quantity'] = (float) $item['available_quantity'];
}

api_ok(['items' => $items, 'total' => round($total, 2), 'count' => count($items)]);
