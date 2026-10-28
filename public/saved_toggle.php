<?php
require __DIR__ . '/../src/bootstrap.php';

// Self-contained copy, the same pattern partials/footer.php uses: this is an
// action endpoint with no page layout, so it cannot inherit a $copy block.
// Every string here was previously hardcoded English, so a Tagalog user saving a
// listing saw English. The JSON 'message' is also what the toast displays, so an
// absent message means no toast at all -- deliberately, rather than a fallback
// string that would be untranslated.
$savedCopy = [
    'en' => [
        'login_required'  => 'Log in or create an account to save listings.',
        'buyer_only'      => 'Only buyers can save listings.',
        'not_found'       => 'Listing not found.',
        'removed'         => 'Removed from saved.',
        'saved'           => 'Saved for later.',
        'update_failed'   => 'Could not update saved. Please try again.',
    ],
    'tl' => [
        'login_required'  => 'Mag-log in o gumawa ng account para mag-save ng listing.',
        'buyer_only'      => 'Mga buyer lamang ang maaaring mag-save ng listing.',
        'not_found'       => 'Hindi nahanap ang listing.',
        'removed'         => 'Inalis mula sa mga nai-save.',
        'saved'           => 'Ni-save para sa susunod.',
        'update_failed'   => 'Hindi na-update ang nai-save. Pakisubukan muli.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$listingId = (int) ($_POST['listing_id'] ?? 0);
$return = is_safe_path((string) ($_POST['return_to'] ?? '')) ? (string) $_POST['return_to'] : 'browse.php';
$loginUrl = login_url($return);
$isAsync = strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'fetch') === 0;

/** Answer the caller: JSON for fetch, otherwise set the flash and redirect. */
$respond = function (bool $ok, ?bool $saved, string $redirect, ?string $message = null) use ($isAsync): void {
    if ($isAsync) {
        header('Content-Type: application/json');
        $payload = $ok
            ? ['ok' => true, 'saved' => $saved, 'message' => $message]
            : ['ok' => false, 'redirect' => $redirect, 'message' => $message];
        echo json_encode($payload);
        exit;
    }
    if ($message !== null) {
        flash_set($ok ? 'success' : 'error', $message);
    }
    redirect_to($redirect);
};

if (!current_user_id()) {
    $msg = t($savedCopy, 'login_required');
    flash_set('error', $msg);
    $respond(false, null, $loginUrl, $msg);
}
if (current_user_role() === 'farmer') {
    $msg = t($savedCopy, 'buyer_only');
    flash_set('error', $msg);
    $respond(false, null, $loginUrl, $msg);
}

$pdo = DB::pdo();

$stmt = $pdo->prepare('SELECT id FROM produce_listings WHERE id = :id AND status = \'active\'');
$stmt->execute(['id' => $listingId]);
if (!$stmt->fetch()) {
    $msg = t($savedCopy, 'not_found');
    flash_set('error', $msg);
    $respond(false, null, $return, $msg);
}

$pdo->beginTransaction();
try {
    $del = $pdo->prepare('DELETE FROM saved_listings WHERE user_id = :user AND listing_id = :listing');
    $del->execute(['user' => current_user_id(), 'listing' => $listingId]);
    $nowSaved = false;
    if ($del->rowCount() > 0) {
        $msg = t($savedCopy, 'removed');
    } else {
        $priceStmt = $pdo->prepare('SELECT price_per_unit FROM produce_listings WHERE id = :id');
        $priceStmt->execute(['id' => $listingId]);
        $savePrice = $priceStmt->fetchColumn();
        $pdo->prepare('INSERT INTO saved_listings (user_id, listing_id, price_at_save) VALUES (:user, :listing, :price)')
            ->execute(['user' => current_user_id(), 'listing' => $listingId, 'price' => $savePrice]);
        $nowSaved = true;
        $msg = t($savedCopy, 'saved');
        // Notify the seller that a buyer saved their listing (engagement signal).
        try {
            $fStmt = $pdo->prepare('SELECT farmer_id, name FROM produce_listings WHERE id = :id');
            $fStmt->execute(['id' => $listingId]);
            $listingRow = $fStmt->fetch();
            if ($listingRow) {
                notify_user($pdo, (int) $listingRow['farmer_id'], 'messages',
                    'Someone saved your listing',
                    (string) $listingRow['name'] . ' was saved by a buyer.',
                    'my_listings.php');
            }
        } catch (Throwable $e) {
            error_log('save notify failed: ' . $e->getMessage());
        }
    }
    $pdo->commit();
    $respond(true, $nowSaved, $return, $msg);
} catch (Throwable $e) {
    $pdo->rollBack();
    // The reason still goes to the log; the page shows the friendly line only.
    error_log('saved_toggle: ' . $e->getMessage());
    $respond(false, null, $return, t($savedCopy, 'update_failed'));
}