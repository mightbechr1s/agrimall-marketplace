<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$listingIds = [];
if (isset($_POST['listing_ids']) && is_array($_POST['listing_ids'])) {
    $listingIds = array_map('intval', $_POST['listing_ids']);
} elseif (isset($_POST['listing_id'])) {
    $listingIds = [(int) $_POST['listing_id']];
}
$listingIds = array_filter($listingIds);

$returnTo  = 'admin_reports.php?t=listings';

if (empty($listingIds)) {
    flash_set('error', 'No listings selected.');
    redirect_to($returnTo);
}

$pdo    = DB::pdo();
$adminId = current_user_id();

try {
    $pdo->beginTransaction();
    $count = 0;
    foreach ($listingIds as $listingId) {
        $stmt = $pdo->prepare('SELECT * FROM produce_listings WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $listingId]);
        $listing = $stmt->fetch();
        if (!$listing) continue;
        if ($listing['status'] === 'sold_out' || $listing['status'] === 'archived') continue;
        $pdo->prepare("UPDATE produce_listings SET status = 'sold_out' WHERE id = :id")
            ->execute(['id' => $listingId]);
        $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, reason, created_at)
             VALUES (:admin, :action, :type, :id, :reason, NOW())'
        )->execute([
            'admin'  => $adminId,
            'action' => 'mark_out_of_stock',
            'type'   => 'listing',
            'id'     => $listingId,
            'reason' => 'Marked out of stock',
        ]);
        $count++;
    }
    $pdo->commit();
    flash_set('success', $count . ' listing(s) marked as out of stock.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', generic_error_message(app_log_exception($e, 'admin_listing_action')));
}
redirect_to($returnTo);
