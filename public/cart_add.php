<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_role('buyer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$listingId = (int) ($_POST['listing_id'] ?? 0);
$quantity  = max(0.0001, (float) ($_POST['quantity'] ?? 1));

$pdo = DB::pdo();
$stmt = $pdo->prepare(
    'SELECT pl.*, qu.symbol AS unit_symbol FROM produce_listings pl
     JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     WHERE pl.id = :id AND pl.status = "active" AND fp.verification_status = "approved"'
);
$stmt->execute(['id' => $listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    flash_set('error', 'Listing not found or not available.');
    redirect_to('browse.php');
}
if ((int) $listing['farmer_id'] === current_user_id()) {
    flash_set('error', 'You cannot add your own listing to the cart.');
    redirect_to('listing.php?id=' . $listingId);
}
$min = (float) $listing['minimum_order_qty'];
$max = $listing['maximum_order_qty'] === null ? null : (float) $listing['maximum_order_qty'];
if ($quantity < $min) {
    flash_set('error', 'Minimum order is ' . $listing['minimum_order_qty'] . ' ' . $listing['unit_symbol'] . '.');
    redirect_to('listing.php?id=' . $listingId);
}
if ($max !== null && $quantity > $max) {
    flash_set('error', 'Maximum order is ' . $listing['maximum_order_qty'] . ' ' . $listing['unit_symbol'] . '.');
    redirect_to('listing.php?id=' . $listingId);
}
if ($quantity > (float) $listing['available_quantity']) {
    flash_set('error', 'Only ' . $listing['available_quantity'] . ' ' . $listing['unit_symbol'] . ' available.');
    redirect_to('listing.php?id=' . $listingId);
}

$pdo->beginTransaction();
try {
    $upsert = $pdo->prepare(
        'INSERT INTO cart_items (buyer_id, listing_id, quantity)
         VALUES (:buyer, :listing, :qty)
         ON DUPLICATE KEY UPDATE quantity = cart_items.quantity + VALUES(quantity), updated_at = CURRENT_TIMESTAMP'
    );
    $upsert->execute(['buyer' => current_user_id(), 'listing' => $listingId, 'qty' => $quantity]);
    $pdo->commit();
    flash_set('success', 'Added to cart.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', 'Could not add to cart. Please try again.');
}
redirect_to('browse.php');