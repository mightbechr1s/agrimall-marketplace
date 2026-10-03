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
$quantity  = number_in($_POST['quantity'] ?? null, 0.0001, MAX_QUANTITY);
$method    = ($_POST['delivery_method'] ?? 'pickup') === 'farmer_delivery' ? 'farmer_delivery' : 'pickup';
$address   = trim((string) ($_POST['delivery_address'] ?? ''));
$pickupDate = (string) ($_POST['preferred_pickup_date'] ?? '');
$pickupTime = (string) ($_POST['preferred_pickup_time'] ?? '');
$note      = trim((string) ($_POST['buyer_note'] ?? ''));

$pdo = DB::pdo();

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT pl.*, fp.verification_status AS farm_verification, qu.symbol AS unit_symbol
         FROM produce_listings pl
         JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
         JOIN quantity_units qu ON pl.unit_id = qu.id
         WHERE pl.id = :id FOR UPDATE'
    );
    $stmt->execute(['id' => $listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        throw new RuntimeException('Listing not found.');
    }
    if ($listing['status'] !== 'active') {
        throw new RuntimeException('This listing is not available for ordering.');
    }
    if ((int) $listing['farmer_id'] === current_user_id()) {
        throw new RuntimeException('You cannot order your own listing.');
    }
    if ($listingId <= 0 || $quantity === null) {
        throw new RuntimeException('Enter a valid quantity.');
    }
    $min = (float) $listing['minimum_order_qty'];
    $max = $listing['maximum_order_qty'] === null ? null : (float) $listing['maximum_order_qty'];
    if ($quantity < $min) {
        throw new RuntimeException('Minimum order is ' . qty_str($listing['minimum_order_qty']) . ' ' . e($listing['unit_symbol']) . '.');
    }
    if ($max !== null && $quantity > $max) {
        throw new RuntimeException('Maximum order is ' . qty_str($listing['maximum_order_qty']) . ' ' . e($listing['unit_symbol']) . '.');
    }
    if ($quantity > (float) $listing['available_quantity']) {
        throw new RuntimeException('Only ' . qty_str($listing['available_quantity']) . ' ' . e($listing['unit_symbol']) . ' available.');
    }
    if ($method === 'farmer_delivery' && mb_strlen($address) < 5) {
        throw new RuntimeException('Please provide a delivery address for farmer delivery.');
    }
    if (mb_strlen($address) > FIELD_LIMITS['delivery_address']) {
        throw new RuntimeException('Delivery address is too long (max ' . FIELD_LIMITS['delivery_address'] . ' characters).');
    }
    if (mb_strlen($note) > FIELD_LIMITS['buyer_note']) {
        throw new RuntimeException('Note is too long (max ' . FIELD_LIMITS['buyer_note'] . ' characters).');
    }

    $reference = order_ref();
    $stmt = $pdo->prepare(
        'INSERT INTO orders
            (order_number, buyer_id, farmer_id, listing_id, quantity, unit_price, delivery_fee,
             delivery_method, delivery_address, preferred_pickup_date, preferred_pickup_time,
             buyer_note, status)
         VALUES
            (:order_number, :buyer_id, :farmer_id, :listing_id, :quantity, :unit_price, :delivery_fee,
             :delivery_method, :delivery_address, :pickup_date, :pickup_time, :buyer_note, :status)'
    );
    $stmt->execute([
        'order_number'    => $reference,
        'buyer_id'        => current_user_id(),
        'farmer_id'       => $listing['farmer_id'],
        'listing_id'      => $listingId,
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
    order_log_status($pdo, $orderId, null, $pdo->lastInsertId() || true ? ($method === 'farmer_delivery' ? 'delivery_fee_review' : 'pending') : 'pending', current_user_id(), 'buyer', $method === 'farmer_delivery' ? 'Awaiting farmer delivery-fee quote.' : 'Order requested.');

    $pdo->commit();
    flash_set('success', 'Order request sent to the farm.');
    redirect_to('/order.php?id=' . $orderId);
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', $e->getMessage());
    redirect_to('/listing.php?id=' . $listingId);
}