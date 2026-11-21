<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
if (!in_array($user['role'], ['buyer', 'admin', 'manager'], true)) {
    api_error('Only buyers can place orders.', 403);
}

$input = api_input();
$method = ($input['delivery_method'] ?? 'pickup') === 'farmer_delivery' ? 'farmer_delivery' : 'pickup';
$address = trim((string) ($input['delivery_address'] ?? ''));
$pickupDate = (string) ($input['preferred_pickup_date'] ?? '');
$pickupTime = (string) ($input['preferred_pickup_time'] ?? '');
$note = trim((string) ($input['buyer_note'] ?? ''));

if ($method === 'farmer_delivery' && mb_strlen($address) < 5) {
    api_error('Delivery address is required.');
}
if (mb_strlen($address) > FIELD_LIMITS['delivery_address']) {
    api_error(sprintf('Delivery address must be at most %d characters.', FIELD_LIMITS['delivery_address']));
}
if (mb_strlen($note) > FIELD_LIMITS['buyer_note']) {
    api_error(sprintf('Note must be at most %d characters.', FIELD_LIMITS['buyer_note']));
}

$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    // Same gate as the web checkout: only active listings from approved farms.
    $stmt = $pdo->prepare(
        "SELECT ci.id AS cart_id, ci.quantity, pl.id AS listing_id, pl.name, pl.price_per_unit,
                pl.farmer_id, pl.minimum_order_qty, pl.maximum_order_qty, pl.available_quantity
         FROM cart_items ci
         JOIN produce_listings pl ON ci.listing_id = pl.id
         JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
         WHERE ci.buyer_id = :buyer AND pl.status = 'active' AND fp.verification_status = 'approved'
         FOR UPDATE"
    );
    $stmt->execute(['buyer' => $user['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new UserFacingException('No available items in your cart.');
    }

    $created = 0;
    $firstOrderId = null;
    foreach ($items as $item) {
        $qty = (float) $item['quantity'];
        $min = (float) $item['minimum_order_qty'];
        $max = $item['maximum_order_qty'] === null ? null : (float) $item['maximum_order_qty'];
        if ($qty < $min || ($max !== null && $qty > $max) || $qty > (float) $item['available_quantity']) {
            throw new UserFacingException('Order limits changed for ' . $item['name'] . '. Review your cart.');
        }
        if ((int) $item['farmer_id'] === (int) $user['id']) {
            throw new UserFacingException('You cannot buy your own listing.');
        }

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
            'price' => (float) $item['price_per_unit'],
            'method' => $method,
            'addr' => $method === 'farmer_delivery' ? $address : null,
            'pdate' => $method === 'pickup' && $pickupDate !== '' ? $pickupDate : null,
            'ptime' => $method === 'pickup' && $pickupTime !== '' ? $pickupTime : null,
            'note' => $note !== '' ? $note : null,
            'status' => $status,
        ]);

        $orderId = (int) $pdo->lastInsertId();
        if ($firstOrderId === null) {
            $firstOrderId = $orderId;
        }
        order_log_status($pdo, $orderId, null, $status, (int) $user['id'], 'buyer',
            $method === 'farmer_delivery' ? 'Awaiting farmer delivery-fee quote.' : 'Order requested.');

        // Stock is deducted when the farmer confirms, same as the web flow.
        $created++;
    }

    $pdo->prepare('DELETE FROM cart_items WHERE buyer_id = :buyer')->execute(['buyer' => $user['id']]);

    $pdo->commit();
    api_ok([
        'order_id' => $firstOrderId,
        'orders_created' => $created,
        'message' => $created === 1 ? 'Order placed.' : $created . ' orders placed.',
    ]);
} catch (UserFacingException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('checkout: ' . $e->getMessage());
    api_error('Checkout failed. Please try again.', 500);
}
