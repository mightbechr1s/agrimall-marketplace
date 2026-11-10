<?php
require __DIR__ . '/_bootstrap.php';

$pdo = DB::pdo();
$user = api_require_auth();

$stmt = $pdo->prepare(
    'SELECT sl.listing_id, pl.name, pl.price_per_unit, pl.unit_id, qu.symbol, fp.farm_name,
            (SELECT li.image_path FROM listing_images li WHERE li.listing_id = pl.id AND li.is_primary = 1 ORDER BY li.sort_order LIMIT 1) AS primary_image,
            sl.price_at_save
     FROM saved_listings sl
     JOIN produce_listings pl ON sl.listing_id = pl.id
     JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     WHERE sl.user_id = :uid
     ORDER BY sl.created_at DESC'
);
$stmt->execute(['uid' => $user['id']]);
$saved = $stmt->fetchAll(PDO::FETCH_ASSOC);

$formatted = array_map(function ($s) {
    return [
        'listing_id' => (int) $s['listing_id'],
        'name' => $s['name'],
        'price' => (float) $s['price_per_unit'],
        'unit' => $s['symbol'],
        'farm_name' => $s['farm_name'],
        'image' => $s['primary_image'],
        'price_at_save' => $s['price_at_save'] ? (float) $s['price_at_save'] : null,
        'price_dropped' => $s['price_at_save'] && (float) $s['price_at_save'] > (float) $s['price_per_unit'],
    ];
}, $saved);

api_ok(['saved' => $formatted, 'count' => count($formatted)]);
