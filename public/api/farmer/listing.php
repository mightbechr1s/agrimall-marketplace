<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
if (!in_array($user['role'], ['farmer', 'admin', 'manager'], true)) {
    api_error('Farmer access only.', 403);
}

$input = api_input();
$listingId = (int) ($input['id'] ?? 0);
if ($listingId <= 0) {
    api_error('Invalid listing.');
}

$pdo = DB::pdo();
$pdo->beginTransaction();
$stmt = $pdo->prepare('SELECT * FROM produce_listings WHERE id = :id AND farmer_id = :fid FOR UPDATE');
$stmt->execute(['id' => $listingId, 'fid' => (int) $user['id']]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$listing) {
    $pdo->rollBack();
    api_error('Listing not found.', 404);
}

if (in_array($listing['status'], ['archived', 'admin_deactivated'], true)) {
    $pdo->rollBack();
    api_error('This listing is closed and cannot be edited.');
}

$updates = [];
$params = ['id' => $listingId];

if (array_key_exists('price', $input)) {
    $price = (float) $input['price'];
    if ($price <= 0 || $price > 1000000) {
        $pdo->rollBack();
        api_error('Enter a price between 0.01 and 1,000,000.');
    }
    $updates[] = 'price_per_unit = :price';
    $params['price'] = round($price, 2);
}

if (array_key_exists('quantity', $input)) {
    $quantity = (float) $input['quantity'];
    if ($quantity < 0 || $quantity > 1000000) {
        $pdo->rollBack();
        api_error('Enter a quantity between 0 and 1,000,000.');
    }
    $updates[] = 'available_quantity = :qty';
    $params['qty'] = round($quantity, 4);

    // Keep the status honest when stock runs out or comes back.
    if ($quantity <= 0 && $listing['status'] === 'active') {
        $updates[] = "status = 'sold_out'";
    } elseif ($quantity > 0 && $listing['status'] === 'sold_out') {
        $updates[] = "status = 'active'";
    }
}

if (array_key_exists('status', $input)) {
    $status = (string) $input['status'];
    if (!in_array($status, ['active', 'paused'], true)) {
        $pdo->rollBack();
        api_error('Status must be active or paused.');
    }
    if ($status === 'active' && (float) ($params['qty'] ?? $listing['available_quantity']) <= 0) {
        $pdo->rollBack();
        api_error('Add stock before publishing this listing.');
    }
    $updates[] = 'status = :status';
    $params['status'] = $status;
}

if (!$updates) {
    $pdo->rollBack();
    api_error('Nothing to update.');
}

$pdo->prepare('UPDATE produce_listings SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = :id')
    ->execute($params);
$pdo->commit();

$fresh = $pdo->prepare('SELECT id, price_per_unit, available_quantity, status FROM produce_listings WHERE id = :id');
$fresh->execute(['id' => $listingId]);
$row = $fresh->fetch(PDO::FETCH_ASSOC);

api_ok([
    'listing' => [
        'id' => (int) $row['id'],
        'price' => (float) $row['price_per_unit'],
        'available_qty' => (float) $row['available_quantity'],
        'status' => $row['status'],
    ],
    'message' => 'Listing updated.',
]);
