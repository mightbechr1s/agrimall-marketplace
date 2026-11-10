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

// Bot detection (honeypot + time token issued on listing.php)
if (is_bot_request('order_request', $_POST, 'website', 0.0)) {
    http_response_code(400);
    exit('Invalid request.');
}
$ordersCopy = $i18nAll['orders'] ?? [];
$listingActionsCopy = $i18nAll['listing_actions'] ?? [];
$adminVerifyCopy = $i18nAll['admin_verify'] ?? [];
$cartCopy = $i18nAll['cart'] ?? [];

$listingId = (int) ($_POST['listing_id'] ?? 0);
$quantity  = number_in($_POST['quantity'] ?? null, 0.0001, MAX_QUANTITY);
$method    = ($_POST['delivery_method'] ?? 'pickup') === 'farmer_delivery' ? 'farmer_delivery' : 'pickup';
$address   = mb_substr(trim((string) ($_POST['delivery_address'] ?? '')), 0, FIELD_LIMITS['delivery_address'] ?? 500);
$pickupDate = (string) ($_POST['preferred_pickup_date'] ?? '');
$pickupTime = (string) ($_POST['preferred_pickup_time'] ?? '');
$note      = mb_substr(trim((string) ($_POST['buyer_note'] ?? '')), 0, FIELD_LIMITS['buyer_note'] ?? 500);

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
        throw new UserFacingException(t($ordersCopy, 'order_not_found'));
    }
    if ($listing['status'] !== 'active') {
        throw new UserFacingException(t($ordersCopy, 'listing_unavailable'));
    }
    if (listing_is_expired($listing)) {
        throw new UserFacingException(t($ordersCopy, 'listing_expired_order'));
    }
    if ((int) $listing['farmer_id'] === current_user_id()) {
        throw new UserFacingException(t($ordersCopy, 'own_listing_order'));
    }
    if ($listingId <= 0 || $quantity === null) {
        throw new UserFacingException(t($cartCopy, 'invalid_qty'));
    }
    $min = (float) $listing['minimum_order_qty'];
    $max = $listing['maximum_order_qty'] === null ? null : (float) $listing['maximum_order_qty'];
    if ($quantity < $min) {
        throw new UserFacingException(sprintf(t($cartCopy, 'min_order'), qty_str($listing['minimum_order_qty']), e($listing['unit_symbol'])));
    }
    if ($max !== null && $quantity > $max) {
        throw new UserFacingException(sprintf(t($cartCopy, 'max_order'), qty_str($listing['maximum_order_qty']), e($listing['unit_symbol'])));
    }
    if ($quantity > (float) $listing['available_quantity']) {
        throw new UserFacingException(sprintf(t($cartCopy, 'only_available'), qty_str($listing['available_quantity']), e($listing['unit_symbol'])));
    }
    if ($method === 'farmer_delivery' && mb_strlen($address) < 5) {
        throw new UserFacingException(t($cartCopy, 'address_required'));
    }
    if (mb_strlen($address) > FIELD_LIMITS['delivery_address']) {
        throw new UserFacingException(sprintf(t($cartCopy, 'address_too_long'), FIELD_LIMITS['delivery_address']));
    }
    if (mb_strlen($note) > FIELD_LIMITS['buyer_note']) {
        throw new UserFacingException(sprintf(t($cartCopy, 'note_too_long'), FIELD_LIMITS['buyer_note']));
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
    flash_set('success', t($ordersCopy, 'request_sent'));
    redirect_to('/order.php?id=' . $orderId);
} catch (UserFacingException $e) {
    $pdo->rollBack();
    flash_set('error', $e->getMessage());
    redirect_to('/listing.php?id=' . $listingId);
} catch (Throwable $e) {
    // Unexpected: its text can name tables, columns or SQL, so log it and
    // show only a reference the user can quote to support.
    $pdo->rollBack();
    flash_set('error', generic_error_message(app_log_exception($e, 'order_request')));
    redirect_to('/listing.php?id=' . $listingId);
}