<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
$i18nAll = include __DIR__ . '/../src/i18n_copy.php';
$ordersCopy = $i18nAll['orders'] ?? [];
$listingActionsCopy = $i18nAll['listing_actions'] ?? [];
$adminVerifyCopy = $i18nAll['admin_verify'] ?? [];
$cartCopy = $i18nAll['cart'] ?? [];

$listingId = (int) ($_POST['listing_id'] ?? 0);
$action    = $_POST['action'] ?? '';

// Return to whichever page sent the action, so a pause from the edit form comes
// back to the form instead of discarding edits in progress.
$returnTo = is_safe_path((string) ($_POST['return_to'] ?? ''))
    ? (string) $_POST['return_to']
    : 'dashboard.php#listings';

if (!$listingId || !$action) {
    flash_set('error', t($listingActionsCopy, 'invalid_request'));
    redirect_to('/dashboard.php');
}

if (current_user_role() !== 'farmer') {
    flash_set('error', t($listingActionsCopy, 'farmer_only'));
    redirect_to('/dashboard.php');
}

$pdo     = DB::pdo();
$userId  = current_user_id();

try {
    $pdo->beginTransaction();

    // Lock first: the status decides which actions are legal (NFR-REL-002).
    $stmt = $pdo->prepare('SELECT * FROM produce_listings WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        throw new UserFacingException(t($listingActionsCopy, 'not_found'));
    }
    if ((int) $listing['farmer_id'] !== $userId) {
        throw new UserFacingException(t($listingActionsCopy, 'own_only'));
    }

    switch ($action) {
        case 'pause':
            if ($listing['status'] !== 'active') {
                throw new UserFacingException(t($listingActionsCopy, 'must_be_active'));
            }
            $newStatus = 'paused';
            break;

        case 'resume':
            if ($listing['status'] !== 'paused') {
                throw new UserFacingException(t($listingActionsCopy, 'must_be_paused'));
            }
            $newStatus = 'active';
            break;

        case 'delete':
            // Archive, never a row delete: orders point at the listing with
            // ON DELETE RESTRICT, and deleting would also cascade away its
            // stock history. Archived rows are kept for the order trail.
            if ($listing['status'] === 'archived') {
                throw new UserFacingException(t($listingActionsCopy, 'already_archived'));
            }
            $newStatus = 'archived';
            break;

        default:
            throw new UserFacingException(sprintf(t($listingActionsCopy, 'unknown_action'), $action));
    }

    $pdo->prepare('UPDATE produce_listings SET status = :status WHERE id = :id')
        ->execute(['status' => $newStatus, 'id' => $listingId]);

    $pdo->commit();
    flash_set('success', sprintf(t($listingActionsCopy, 'status_now'), str_replace('_', ' ', $newStatus)));
    redirect_to($returnTo);

} catch (UserFacingException $e) {
    $pdo->rollBack();
    flash_set('error', $e->getMessage());
    redirect_to($returnTo);
} catch (Throwable $e) {
    // Unexpected: its text can name tables, columns or SQL, so log it and
    // show only a reference the user can quote to support.
    $pdo->rollBack();
    flash_set('error', generic_error_message(app_log_exception($e, 'listing_action')));
    redirect_to($returnTo);
}
