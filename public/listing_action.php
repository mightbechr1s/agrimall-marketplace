<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$listingId = (int) ($_POST['listing_id'] ?? 0);
$action    = $_POST['action'] ?? '';

// Return to whichever page sent the action, so a pause from the edit form comes
// back to the form instead of discarding edits in progress.
$returnTo = is_safe_path((string) ($_POST['return_to'] ?? ''))
    ? (string) $_POST['return_to']
    : 'dashboard.php#listings';

if (!$listingId || !$action) {
    flash_set('error', 'Invalid request.');
    redirect_to('/dashboard.php');
}

if (current_user_role() !== 'farmer') {
    flash_set('error', 'Only a farmer can manage listings.');
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
        throw new UserFacingException('Listing not found.');
    }
    if ((int) $listing['farmer_id'] !== $userId) {
        throw new UserFacingException('You can only manage your own listings.');
    }

    switch ($action) {
        case 'pause':
            if ($listing['status'] !== 'active') {
                throw new UserFacingException('Only an active listing can be paused.');
            }
            $newStatus = 'paused';
            break;

        case 'resume':
            if ($listing['status'] !== 'paused') {
                throw new UserFacingException('Only a paused listing can be resumed.');
            }
            $newStatus = 'active';
            break;

        case 'delete':
            // Archive, never a row delete: orders point at the listing with
            // ON DELETE RESTRICT, and deleting would also cascade away its
            // stock history. Archived rows are kept for the order trail.
            if ($listing['status'] === 'archived') {
                throw new UserFacingException('This listing is already archived.');
            }
            $newStatus = 'archived';
            break;

        default:
            throw new UserFacingException('Unknown action: ' . $action);
    }

    $pdo->prepare('UPDATE produce_listings SET status = :status WHERE id = :id')
        ->execute(['status' => $newStatus, 'id' => $listingId]);

    $pdo->commit();
    flash_set('success', 'Listing is now ' . str_replace('_', ' ', $newStatus) . '.');
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
