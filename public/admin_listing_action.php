<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$listingId = (int) ($_POST['listing_id'] ?? 0);
$reason    = trim((string) ($_POST['reason'] ?? ''));
$returnTo  = 'admin_reports.php?t=listings';

if (!$listingId || $reason === '') {
    flash_set('error', 'Listing ID and a reason are required.');
    redirect_to($returnTo);
}
if (mb_strlen($reason) > FIELD_LIMITS['reason']) {
    flash_set('error', 'Reason is too long (max ' . FIELD_LIMITS['reason'] . ' characters).');
    redirect_to($returnTo);
}

$pdo    = DB::pdo();
$adminId = current_user_id();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM produce_listings WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $listingId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        throw new UserFacingException('Listing not found.');
    }
    if ($listing['status'] === 'admin_deactivated') {
        throw new UserFacingException('This listing is already deactivated.');
    }
    if ($listing['status'] === 'archived') {
        throw new UserFacingException('Archived listings cannot be deactivated.');
    }

    $pdo->prepare("UPDATE produce_listings SET status = 'admin_deactivated' WHERE id = :id")
        ->execute(['id' => $listingId]);
    $pdo->prepare(
        'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, reason, created_at)
         VALUES (:admin, :action, :type, :id, :reason, NOW())'
    )->execute([
        'admin'  => $adminId,
        'action' => 'deactivate_listing',
        'type'   => 'listing',
        'id'     => $listingId,
        'reason' => $reason,
    ]);

    $pdo->commit();
    flash_set('success', 'Listing deactivated.');
} catch (UserFacingException $e) {
    $pdo->rollBack();
    flash_set('error', $e->getMessage());
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', generic_error_message(app_log_exception($e, 'admin_listing_action')));
}
redirect_to($returnTo);
