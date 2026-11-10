<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_role('buyer', 'admin', 'manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
$i18nAll = include __DIR__ . '/../src/i18n_copy.php';
$cartCopy = $i18nAll['cart'] ?? [];

$action = $_POST['action'] ?? '';
$itemId = (int) ($_POST['item_id'] ?? 0);
$quantity = number_in($_POST['quantity'] ?? null, 0.0001, MAX_QUANTITY);
$buyerId = current_user_id();
$pdo = DB::pdo();

if ($action === 'remove' && $itemId > 0) {
    $pdo->prepare('DELETE FROM cart_items WHERE id = :id AND buyer_id = :buyer')
        ->execute(['id' => $itemId, 'buyer' => $buyerId]);
    flash_set('success', t($cartCopy, 'item_removed'));
    redirect_to('cart.php');
}

if ($action === 'clear') {
    $pdo->prepare('DELETE FROM cart_items WHERE buyer_id = :buyer')
        ->execute(['buyer' => $buyerId]);
    flash_set('success', t($cartCopy, 'cart_cleared'));
    redirect_to('cart.php');
}

if ($action === 'update' && $itemId > 0 && $quantity !== null) {
    $stmt = $pdo->prepare(
        'SELECT ci.id, ci.listing_id, ci.quantity, pl.minimum_order_qty, pl.maximum_order_qty, pl.available_quantity, qu.symbol
         FROM cart_items ci
         JOIN produce_listings pl ON ci.listing_id = pl.id
         JOIN quantity_units qu ON pl.unit_id = qu.id
         WHERE ci.id = :id AND ci.buyer_id = :buyer'
    );
    $stmt->execute(['id' => $itemId, 'buyer' => $buyerId]);
    $item = $stmt->fetch();
    if ($item) {
        $min = (float) $item['minimum_order_qty'];
        $max = $item['maximum_order_qty'] === null ? null : (float) $item['maximum_order_qty'];
        if ($quantity < $min || ($max !== null && $quantity > $max) || $quantity > (float) $item['available_quantity']) {
            flash_set('error', sprintf(t($cartCopy, 'qty_range'), $min, ($max ?? $item['available_quantity']), $item['symbol']));
            redirect_to('cart.php');
        }
        $pdo->prepare('UPDATE cart_items SET quantity = :qty, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND buyer_id = :buyer')
            ->execute(['qty' => $quantity, 'id' => $itemId, 'buyer' => $buyerId]);
        flash_set('success', t($cartCopy, 'cart_updated'));
    }
}

redirect_to('cart.php');