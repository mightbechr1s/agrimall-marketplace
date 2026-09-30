<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_role('buyer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$method      = ($_POST['delivery_method'] ?? 'pickup') === 'farmer_delivery' ? 'farmer_delivery' : 'pickup';
$address     = trim((string) ($_POST['delivery_address'] ?? ''));
$pickupDate  = (string) ($_POST['preferred_pickup_date'] ?? '');
$pickupTime  = (string) ($_POST['preferred_pickup_time'] ?? '');
$note        = trim((string) ($_POST['buyer_note'] ?? ''));

$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    $cartStmt = $pdo->prepare(
        'SELECT ci.id AS cart_id, ci.listing_id, ci.quantity
         FROM cart_items ci
         JOIN produce_listings pl ON ci.listing_id = pl.id
         JOIN quantity_units qu ON pl.unit_id = qu.id
         JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
         WHERE ci.buyer_id = :buyer AND pl.status = \'active\' AND fp.verification_status = \'approved\'
         FOR UPDATE'
    );
    $cartStmt->execute(['buyer' => current_user_id()]);
    $items = $cartStmt->fetchAll();
    if (!$items) {
        flash_set('error', 'Your cart is empty.');
        redirect_to('cart.php');
    }
    if ($method === 'farmer_delivery' && mb_strlen($address) < 5) {
        flash_set('error', 'Please provide a delivery address for farmer delivery.');
        redirect_to('cart.php');
    }
    if (mb_strlen($address) > FIELD_LIMITS['delivery_address']) {
        flash_set('error', 'Delivery address is too long (max ' . FIELD_LIMITS['delivery_address'] . ' characters).');
        redirect_to('cart.php');
    }
    if (mb_strlen($note) > FIELD_LIMITS['buyer_note']) {
        flash_set('error', 'Note is too long (max ' . FIELD_LIMITS['buyer_note'] . ' characters).');
        redirect_to('cart.php');
    }

    $created = 0;
    $firstOrderId = null;
    foreach ($items as $item) {
        $stmt = $pdo->prepare('SELECT * FROM produce_listings WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $item['listing_id']]);
        $listing = $stmt->fetch();
        if (!$listing || $listing['status'] !== 'active') {
            continue;
        }
        $quantity = (float) $item['quantity'];
        $min = (float) $listing['minimum_order_qty'];
        $max = $listing['maximum_order_qty'] === null ? null : (float) $listing['maximum_order_qty'];
        if ($quantity < $min || ($max !== null && $quantity > $max) || $quantity > (float) $listing['available_quantity']) {
            $pdo->rollBack();
            flash_set('error', 'One or more items fail order limits: ' . $listing['name'] . '. Please review your cart.');
            redirect_to('cart.php');
        }

        $reference = order_ref();
        $pdo->prepare(
            'INSERT INTO orders
                (order_number, buyer_id, farmer_id, listing_id, quantity, unit_price, delivery_fee,
                 delivery_method, delivery_address, preferred_pickup_date, preferred_pickup_time,
                 buyer_note, status)
             VALUES
                (:order_number, :buyer_id, :farmer_id, :listing_id, :quantity, :unit_price, :delivery_fee,
                 :delivery_method, :delivery_address, :pickup_date, :pickup_time, :buyer_note, :status)'
        )->execute([
            'order_number'    => $reference,
            'buyer_id'        => current_user_id(),
            'farmer_id'       => $listing['farmer_id'],
            'listing_id'      => $listing['id'],
            'quantity'        => $quantity,
            'unit_price'      => $listing['price_per_unit'],
            'delivery_fee'    => 0.00,
            'delivery_method' => $method,
            'delivery_address'=> $method === 'farmer_delivery' ? $address : null,
            'pickup_date'     => $method === 'pickup' && $pickupDate !== '' ? $pickupDate : null,
            'pickup_time'     => $method === 'pickup' && $pickupTime !== '' ? $pickupTime : null,
            'buyer_note'      => $note !== '' ? $note : null,
            'status'          => $method === 'farmer_delivery' ? 'delivery_fee_review' : 'pending',
        ]);
        $orderId = (int) $pdo->lastInsertId();
        if ($firstOrderId === null) {
            $firstOrderId = $orderId;
        }
        order_log_status($pdo, $orderId, null, $method === 'farmer_delivery' ? 'delivery_fee_review' : 'pending', current_user_id(), 'buyer', $method === 'farmer_delivery' ? 'Awaiting farmer delivery-fee quote.' : 'Order requested.');
        $created++;
    }

    $pdo->prepare('DELETE FROM cart_items WHERE buyer_id = :buyer')->execute(['buyer' => current_user_id()]);
    $pdo->commit();

    if ($created === 0) {
        flash_set('error', 'No valid items in cart.');
        redirect_to('cart.php');
    }
    flash_set('success', $created . ' order' . ($created > 1 ? 's' : '') . ' placed.');
    redirect_to($firstOrderId ? 'order.php?id=' . $firstOrderId : 'dashboard.php#orders');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', 'Checkout failed. Please try again.');
    redirect_to('cart.php');
}