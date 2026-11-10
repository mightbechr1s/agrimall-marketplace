<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
$input = api_input();
$listingId = (int) ($input['listing_id'] ?? 0);

if ($listingId <= 0) {
    api_error('Invalid listing.');
}

$pdo = DB::pdo();

// Check if already saved
$stmt = $pdo->prepare('SELECT 1 FROM saved_listings WHERE user_id = :u AND listing_id = :l');
$stmt->execute(['u' => $user['id'], 'l' => $listingId]);
$exists = (bool) $stmt->fetchColumn();

if ($exists) {
    $pdo->prepare('DELETE FROM saved_listings WHERE user_id = :u AND listing_id = :l')
        ->execute(['u' => $user['id'], 'l' => $listingId]);
    api_ok(['saved' => false, 'message' => 'Removed from saved.']);
} else {
    // Get current price for tracking
    $priceStmt = $pdo->prepare('SELECT price_per_unit FROM produce_listings WHERE id = :id');
    $priceStmt->execute(['id' => $listingId]);
    $price = $priceStmt->fetchColumn();
    $pdo->prepare('INSERT INTO saved_listings (user_id, listing_id, price_at_save) VALUES (:u, :l, :p)')
        ->execute(['u' => $user['id'], 'l' => $listingId, 'p' => $price]);
    api_ok(['saved' => true, 'message' => 'Saved for later.']);
}
