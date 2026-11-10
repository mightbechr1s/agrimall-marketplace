<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
$input = api_input();
$itemId = (int) ($input['item_id'] ?? 0);
$quantity = (float) ($input['quantity'] ?? 0);

if ($itemId <= 0) {
    api_error('Invalid item.');
}

$pdo = DB::pdo();

// Verify ownership
$stmt = $pdo->prepare('SELECT ci.id, ci.listing_id, pl.minimum_order_qty, pl.available_quantity, pl.maximum_order_qty FROM cart_items ci JOIN produce_listings pl ON pl.id = ci.listing_id WHERE ci.id = :id AND ci.buyer_id = :buyer');
$stmt->execute(['id' => $itemId, 'buyer' => $user['id']]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    api_error('Cart item not found.', 404);
}

if ($quantity <= 0) {
    // Remove
    $pdo->prepare('DELETE FROM cart_items WHERE id = :id AND buyer_id = :buyer')->execute(['id' => $itemId, 'buyer' => $user['id']]);
    api_ok(['message' => 'Item removed.']);
}

$minQty = (float) $item['minimum_order_qty'];
$maxQty = $item['maximum_order_qty'] ? (float) $item['maximum_order_qty'] : (float) $item['available_quantity'];
if ($quantity < $minQty) {
    api_error("Minimum order is {$minQty}.");
}
if ($quantity > $maxQty) {
    api_error("Maximum order is {$maxQty}.");
}
if ($quantity > (float) $item['available_quantity']) {
    api_error("Only {$item['available_quantity']} available.");
}

$pdo->prepare('UPDATE cart_items SET quantity = :qty WHERE id = :id AND buyer_id = :buyer')
    ->execute(['qty' => $quantity, 'id' => $itemId, 'buyer' => $user['id']]);

api_ok(['message' => 'Quantity updated.']);
