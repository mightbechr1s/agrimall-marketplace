<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$listingId = (int) ($_POST['listing_id'] ?? 0);
$return = (string) ($_POST['return_to'] ?? 'browse.php');
$pdo = DB::pdo();

$stmt = $pdo->prepare('SELECT id FROM produce_listings WHERE id = :id AND status = "active"');
$stmt->execute(['id' => $listingId]);
if (!$stmt->fetch()) {
    flash_set('error', 'Listing not found.');
    redirect_to($return);
}

$pdo->beginTransaction();
try {
    $del = $pdo->prepare('DELETE FROM saved_listings WHERE user_id = :user AND listing_id = :listing');
    $del->execute(['user' => current_user_id(), 'listing' => $listingId]);
    if ($del->rowCount() > 0) {
        flash_set('success', 'Removed from saved.');
    } else {
        $pdo->prepare('INSERT INTO saved_listings (user_id, listing_id) VALUES (:user, :listing)')
            ->execute(['user' => current_user_id(), 'listing' => $listingId]);
        flash_set('success', 'Saved for later.');
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', 'Could not update saved. Please try again.');
}
redirect_to($return);