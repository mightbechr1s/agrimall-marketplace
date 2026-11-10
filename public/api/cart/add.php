<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
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

// Verify listing exists and is active
$stmt = $pdo->prepare("SELECT id, minimum_order_qty, available_quantity FROM v_active_listings WHERE id = :id");
$stmt->execute(['id' => $listingId]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$listing) {
    api_error('Listing not found or unavailable.', 404);
}

$minQty = (float) $listing['minimum_order_qty'];
if ($quantity < $minQty) {
    api_error("Minimum order is {$minQty}.");
}
if ($quantity > (float) $listing['available_quantity']) {
    api_error("Only {$listing['available_quantity']} available.");
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
