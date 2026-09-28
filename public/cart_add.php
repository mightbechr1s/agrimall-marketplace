<?php
require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$isAsync = strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'fetch') === 0;

$return = is_safe_path((string) ($_POST['return_to'] ?? '')) ? (string) $_POST['return_to'] : 'browse.php';
$loginUrl = login_url($return);

// Error/success responder: JSON when called via fetch, flash+redirect otherwise.
$respond = function (bool $ok, string $message, string $redirect) use ($isAsync): void {
    if ($isAsync) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $message, 'redirect' => $redirect]);
        exit;
    }
    flash_set($ok ? 'success' : 'error', $message);
    redirect_to($redirect);
};

if (!current_user_id()) {
    $respond(false, 'Log in or create an account to add items to your cart.', $loginUrl);
}
if (current_user_role() !== 'buyer') {
    $respond(false, 'Log in as a buyer to add items to your cart.', $loginUrl);
}

$listingId = (int) ($_POST['listing_id'] ?? 0);
$quantity  = max(0.0001, (float) ($_POST['quantity'] ?? 1));
$buyNow    = !empty($_POST['buy_now']);

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
    $respond(false, 'Listing not found or not available.', 'browse.php');
}
if ((int) $listing['farmer_id'] === current_user_id()) {
    $respond(false, 'You cannot add your own listing to the cart.', 'listing.php?id=' . $listingId);
}
$min = (float) $listing['minimum_order_qty'];
$max = $listing['maximum_order_qty'] === null ? null : (float) $listing['maximum_order_qty'];
if ($quantity < $min) {
    $respond(false, 'Minimum order is ' . $listing['minimum_order_qty'] . ' ' . $listing['unit_symbol'] . '.', 'listing.php?id=' . $listingId);
}
if ($max !== null && $quantity > $max) {
    $respond(false, 'Maximum order is ' . $listing['maximum_order_qty'] . ' ' . $listing['unit_symbol'] . '.', 'listing.php?id=' . $listingId);
}
if ($quantity > (float) $listing['available_quantity']) {
    $respond(false, 'Only ' . $listing['available_quantity'] . ' ' . $listing['unit_symbol'] . ' available.', 'listing.php?id=' . $listingId);
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
    $respond(true, 'Added to cart', $buyNow ? 'cart.php' : 'browse.php');
} catch (Throwable $e) {
    $pdo->rollBack();
    $respond(false, 'Could not add to cart. Please try again.', 'browse.php');
}
if (current_user_role() !== 'buyer') {
    $respond(false, 'Log in as a buyer to add items to your cart.', $loginUrl);
}

$listingId = (int) ($_POST['listing_id'] ?? 0);
$quantity  = max(0.0001, (float) ($_POST['quantity'] ?? 1));
$buyNow    = !empty($_POST['buy_now']);

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
    $respond(false, 'Listing not found or not available.', 'browse.php');
}
if ((int) $listing['farmer_id'] === current_user_id()) {
    $respond(false, 'You cannot add your own listing to the cart.', 'listing.php?id=' . $listingId);
}
$min = (float) $listing['minimum_order_qty'];
$max = $listing['maximum_order_qty'] === null ? null : (float) $listing['maximum_order_qty'];
if ($quantity < $min) {
    $respond(false, 'Minimum order is ' . $listing['minimum_order_qty'] . ' ' . $listing['unit_symbol'] . '.', 'listing.php?id=' . $listingId);
}
if ($max !== null && $quantity > $max) {
    $respond(false, 'Maximum order is ' . $listing['maximum_order_qty'] . ' ' . $listing['unit_symbol'] . '.', 'listing.php?id=' . $listingId);
}
if ($quantity > (float) $listing['available_quantity']) {
    $respond(false, 'Only ' . $listing['available_quantity'] . ' ' . $listing['unit_symbol'] . ' available.', 'listing.php?id=' . $listingId);
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
    $respond(true, 'Added to cart', $buyNow ? 'cart.php' : 'browse.php');
} catch (Throwable $e) {
    $pdo->rollBack();
    $respond(false, 'Could not add to cart. Please try again.', 'browse.php');
}