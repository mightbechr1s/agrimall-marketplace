<?php
require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$listingId = (int) ($_POST['listing_id'] ?? 0);
$return = is_safe_path((string) ($_POST['return_to'] ?? '')) ? (string) $_POST['return_to'] : 'browse.php';
$loginUrl = login_url($return);
$isAsync = strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'fetch') === 0;

$respond = function (bool $ok, ?bool $saved, string $redirect) use ($isAsync): void {
    if ($isAsync) {
        header('Content-Type: application/json');
        echo json_encode($ok ? ['ok' => true, 'saved' => $saved] : ['ok' => false, 'redirect' => $redirect]);
        exit;
    }
    redirect_to($redirect);
};

if (!current_user_id()) {
    flash_set('error', 'Log in or create an account to save listings.');
    $respond(false, null, $loginUrl);
}
if (current_user_role() === 'farmer') {
    flash_set('error', 'Only buyers can save listings.');
    $respond(false, null, $loginUrl);
}

$pdo = DB::pdo();

$stmt = $pdo->prepare('SELECT id FROM produce_listings WHERE id = :id AND status = "active"');
$stmt->execute(['id' => $listingId]);
if (!$stmt->fetch()) {
    flash_set('error', 'Listing not found.');
    $respond(false, null, $return);
}

$pdo->beginTransaction();
try {
    $del = $pdo->prepare('DELETE FROM saved_listings WHERE user_id = :user AND listing_id = :listing');
    $del->execute(['user' => current_user_id(), 'listing' => $listingId]);
    $nowSaved = false;
    if ($del->rowCount() > 0) {
        flash_set('success', 'Removed from saved.');
    } else {
        $pdo->prepare('INSERT INTO saved_listings (user_id, listing_id) VALUES (:user, :listing)')
            ->execute(['user' => current_user_id(), 'listing' => $listingId]);
        $nowSaved = true;
        flash_set('success', 'Saved for later.');
    }
    $pdo->commit();
    $respond(true, $nowSaved, $return);
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', 'Could not update saved. Please try again.');
    $respond(false, null, $return);
}