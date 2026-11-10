<?php
require __DIR__ . '/../_bootstrap.php';

$user = api_require_auth();
if (!in_array($user['role'], ['farmer', 'admin', 'manager'], true)) {
    api_error('Farmer access only.', 403);
}

$pdo = DB::pdo();
$farmerId = (int) $user['id'];

$stmt = $pdo->prepare(
    "SELECT pl.id, pl.name, pl.description, pl.price_per_unit, pl.discount_percent,
            pl.available_quantity, pl.minimum_order_qty, pl.maximum_order_qty,
            pl.harvest_date, pl.expiry_date, pl.is_organic, pl.status, pl.rejection_reason,
            pl.published_at, pl.updated_at,
            pc.name AS category, qu.symbol AS unit,
            (SELECT li.image_path FROM listing_images li WHERE li.listing_id = pl.id AND li.is_primary = 1 ORDER BY li.sort_order LIMIT 1) AS image,
            (pl.expiry_date IS NOT NULL AND pl.expiry_date < CURDATE()) AS is_expired
     FROM produce_listings pl
     JOIN produce_categories pc ON pl.category_id = pc.id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     WHERE pl.farmer_id = :fid
     ORDER BY FIELD(pl.status, 'active', 'paused', 'sold_out', 'draft', 'archived', 'admin_deactivated'), pl.updated_at DESC"
);
$stmt->execute(['fid' => $farmerId]);

$listings = array_map(function ($l) {
    return [
        'id' => (int) $l['id'],
        'name' => $l['name'],
        'category' => $l['category'],
        'price' => (float) $l['price_per_unit'],
        'unit' => $l['unit'],
        'available_qty' => (float) $l['available_quantity'],
        'min_order' => (float) $l['minimum_order_qty'],
        'status' => $l['status'],
        'is_organic' => (bool) $l['is_organic'],
        'is_expired' => (bool) $l['is_expired'],
        'image' => $l['image'],
        'rejection_reason' => $l['rejection_reason'],
        'updated_at' => $l['updated_at'],
    ];
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

api_ok(['listings' => $listings, 'count' => count($listings)]);
