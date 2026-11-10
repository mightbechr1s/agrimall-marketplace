<?php
require __DIR__ . '/../_bootstrap.php';

$pdo = DB::pdo();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    api_error('Invalid listing ID.');
}

$stmt = $pdo->prepare(
    "SELECT pl.*, pc.name AS category_name, pc.slug AS category_slug, qu.symbol AS unit_symbol,
            (pl.expiry_date IS NOT NULL AND pl.expiry_date < CURDATE()) AS is_expired,
            fp.farm_name, fp.barangay, fp.municipality_city, fp.province, fp.verification_status,
            (SELECT li.image_path FROM listing_images li WHERE li.listing_id = pl.id AND li.is_primary = 1 ORDER BY li.sort_order LIMIT 1) AS primary_image
     FROM produce_listings pl
     JOIN produce_categories pc ON pl.category_id = pc.id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
     WHERE pl.id = :id AND pl.status = 'active'"
);
$stmt->execute(['id' => $id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    api_error('Listing not found.', 404);
}

// Images
$imgStmt = $pdo->prepare('SELECT image_path FROM listing_images WHERE listing_id = :id ORDER BY is_primary DESC, sort_order');
$imgStmt->execute(['id' => $id]);
$images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

// Social proof
$saveStmt = $pdo->prepare('SELECT COUNT(*) FROM saved_listings WHERE listing_id = :id');
$saveStmt->execute(['id' => $id]);
$saveCount = (int) $saveStmt->fetchColumn();

$soldStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE listing_id = :id AND status = 'completed' AND completed_at >= (NOW() - INTERVAL 7 DAY)");
$soldStmt->execute(['id' => $id]);
$soldWeek = (int) $soldStmt->fetchColumn();

$authUser = api_auth_user();
$isSaved = false;
if ($authUser) {
    $savedStmt = $pdo->prepare('SELECT 1 FROM saved_listings WHERE user_id = :u AND listing_id = :id');
    $savedStmt->execute(['u' => $authUser['id'], 'id' => $id]);
    $isSaved = (bool) $savedStmt->fetchColumn();
}

// Related listings
$relatedStmt = $pdo->prepare("SELECT v.*, fp.farm_name AS farmer_name FROM v_active_listings v JOIN farmer_profiles fp ON fp.user_id = v.farmer_id WHERE v.category_id = :cat AND v.id <> :id ORDER BY v.is_expired ASC, v.published_at DESC LIMIT 4");
$relatedStmt->execute(['cat' => $item['category_id'], 'id' => $id]);
$related = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

api_ok([
    'listing' => [
        'id' => (int) $item['id'],
        'name' => $item['name'],
        'description' => $item['description'],
        'price' => (float) $item['price_per_unit'],
        'unit' => $item['unit_symbol'],
        'category' => $item['category_name'],
        'category_slug' => $item['category_slug'],
        'available_qty' => (float) $item['available_quantity'],
        'min_order' => (float) $item['minimum_order_qty'],
        'max_order' => $item['maximum_order_qty'] ? (float) $item['maximum_order_qty'] : null,
        'is_organic' => (bool) $item['is_organic'],
        'harvest_date' => $item['harvest_date'],
        'expiry_date' => $item['expiry_date'],
        'is_expired' => (bool) $item['is_expired'],
        'discount' => (int) $item['discount_percent'],
        'images' => $images ?: [$item['primary_image']],
        'primary_image' => $item['primary_image'],
        'seller' => [
            'id' => (int) $item['farmer_id'],
            'farm_name' => $item['farm_name'],
            'barangay' => $item['barangay'],
            'municipality' => $item['municipality_city'],
            'province' => $item['province'],
            'verified' => $item['verification_status'] === 'approved',
        ],
        'save_count' => $saveCount,
        'is_saved' => $isSaved,
        'sold_this_week' => $soldWeek,
    ],
    'related' => array_map(function ($r) {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'price' => (float) $r['price_per_unit'],
            'unit' => $r['unit_symbol'],
            'category' => $r['category_name'],
            'farm_name' => $r['farm_name'],
            'image' => $r['primary_image'],
            'available_qty' => (float) $r['available_quantity'],
        ];
    }, $related),
]);
