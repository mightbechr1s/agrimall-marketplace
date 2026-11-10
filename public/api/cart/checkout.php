<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
$input = api_input();
$pdo = DB::pdo();

// Get cart items
$stmt = $pdo->prepare(
    'SELECT ci.id AS cart_id, ci.quantity, pl.id AS listing_id, pl.price_per_unit, pl.farmer_id,
            pl.minimum_order_qty, pl.available_quantity
     FROM cart_items ci
     JOIN produce_listings pl ON ci.listing_id = pl.id
     WHERE ci.buyer_id = :buyer'
);
$stmt->execute(['buyer' => $user['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    api_error('Cart is empty.');
}

$method = ($input['delivery_method'] ?? 'pickup') === 'farmer_delivery' ? 'farmer_delivery' : 'pickup';
$address = mb_substr(trim((string) ($input['delivery_address'] ?? '')), 0, 500);
$pickupDate = (string) ($input['preferred_pickup_date'] ?? '');
$pickupTime = (string) ($input['preferred_pickup_time'] ?? '');
$note = mb_substr(trim((string) ($input['buyer_note'] ?? '')), 0, 500);

if ($method === 'farmer_delivery' && mb_strlen($address) < 5) {
    api_error('Delivery address is required.');
}

$pdo->beginTransaction();
try {
    foreach ($items as $item) {
        $qty = (float) $item['quantity'];
        $min = (float) $item['minimum_order_qty'];
        if ($qty < $min) {
            throw new UserFacingException("Minimum order is {$min}.");
        }
        if ($qty > (float) $item['available_quantity']) {
            throw new UserFacingException("Only {$item['available_quantity']} available.");
        }

        $unitPrice = (float) $item['price_per_unit'];
        $status = $method === 'farmer_delivery' ? 'delivery_fee_review' : 'pending';

        $pdo->prepare(
            'INSERT INTO orders (order_number, buyer_id, farmer_id, listing_id, quantity, unit_price, delivery_fee, delivery_method, delivery_address, preferred_pickup_date, preferred_pickup_time, buyer_note, status, created_at)
             VALUES (:ref, :buyer, :farmer, :listing, :qty, :price, 0, :method, :addr, :pdate, :ptime, :note, :status, NOW())'
        )->execute([
            'ref' => order_ref(),
            'buyer' => $user['id'],
            'farmer' => $item['farmer_id'],
            'listing' => $item['listing_id'],
            'qty' => $qty,
            'price' => $unitPrice,
            'method' => $method,
            'addr' => $method === 'farmer_delivery' ? $address : null,
            'pdate' => $pickupDate ?: null,
            'ptime' => $pickupTime ?: null,
            'note' => $note ?: null,
            'status' => $status,
        ]);

        $orderId = (int) $pdo->lastInsertId();
        order_log_status($pdo, $orderId, null, $status, (int) $user['id'], 'buyer',
            $method === 'farmer_delivery' ? 'Awaiting farmer delivery-fee quote.' : 'Order requested.');

        // Stock is deducted when the farmer confirms, same as the web flow.
    }

    // Clear cart
    $pdo->prepare('DELETE FROM cart_items WHERE buyer_id = :buyer')->execute(['buyer' => $user['id']]);

    $pdo->commit();
    api_ok(['order_id' => $orderId, 'message' => 'Order placed.']);
} catch (UserFacingException $e) {
    $pdo->rollBack();
    api_error($e->getMessage());
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('checkout: ' . $e->getMessage());
    api_error('Checkout failed. Please try again.', 500);
}
