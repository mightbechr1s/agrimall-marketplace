<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
if (!in_array($user['role'], ['buyer', 'admin', 'manager'], true)) {
    api_error('Only buyers can add items to a cart.', 403);
}

$input = api_input();
$listingId = (int) ($input['listing_id'] ?? 0);
$quantity = (float) ($input['quantity'] ?? 0);

if ($listingId <= 0) {
    api_error('Invalid listing.');
}
if ($quantity <= 0) {
    api_error('Invalid quantity.');
}

$pdo = DB::pdo();

// v_active_listings already requires an active listing from an approved farm.
$stmt = $pdo->prepare(
    'SELECT id, farmer_id, minimum_order_qty, maximum_order_qty, available_quantity, unit_symbol
     FROM v_active_listings WHERE id = :id'
);
$stmt->execute(['id' => $listingId]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$listing) {
    api_error('Listing not found or unavailable.', 404);
}
if ((int) $listing['farmer_id'] === (int) $user['id']) {
    api_error('You cannot buy your own listing.', 403);
}

$existingStmt = $pdo->prepare('SELECT quantity FROM cart_items WHERE buyer_id = :buyer AND listing_id = :listing');
$existingStmt->execute(['buyer' => $user['id'], 'listing' => $listingId]);
$newTotal = $quantity + (float) ($existingStmt->fetchColumn() ?: 0);

$minQty = (float) $listing['minimum_order_qty'];
$maxQty = $listing['maximum_order_qty'] === null ? null : (float) $listing['maximum_order_qty'];
$unit = $listing['unit_symbol'];
if ($newTotal < $minQty) {
    api_error("Minimum order is {$minQty} {$unit}.");
}
if ($maxQty !== null && $newTotal > $maxQty) {
    api_error("Maximum order is {$maxQty} {$unit}.");
}
if ($newTotal > (float) $listing['available_quantity']) {
    api_error("Only {$listing['available_quantity']} {$unit} available.");
}

// Upsert cart item
$pdo->prepare(
    "INSERT INTO cart_items (buyer_id, listing_id, quantity, created_at)
     VALUES (:buyer, :listing, :qty, NOW())
     ON DUPLICATE KEY UPDATE quantity = quantity + :qty2, updated_at = NOW()"
)->execute([
    'buyer' => $user['id'],
    'listing' => $listingId,
    'qty' => $quantity,
    'qty2' => $quantity,
]);

// Get cart count
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE buyer_id = :buyer');
$countStmt->execute(['buyer' => $user['id']]);
$cartCount = (int) $countStmt->fetchColumn();

api_ok(['cart_count' => $cartCount, 'message' => 'Added to cart.']);
