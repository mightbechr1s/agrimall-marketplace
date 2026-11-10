<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_role('farmer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$userId = current_user_id();
$pdo = DB::pdo();
$ids = array_filter(array_map('intval', (array) ($_POST['listing_ids'] ?? [])));
$action = (string) ($_POST['action'] ?? '');

if (!$ids || !in_array($action, ['activate', 'deactivate', 'delete'], true)) {
    flash_set('error', 'No listings selected or invalid action.');
    redirect_to('my_listings.php');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$check = $pdo->prepare("SELECT id FROM produce_listings WHERE id IN ($placeholders) AND farmer_id = ?");
$check->execute(array_merge($ids, [$userId]));
$owned = $check->fetchAll(PDO::FETCH_COLUMN);
if (!$owned) {
    flash_set('error', 'No matching listings found.');
    redirect_to('my_listings.php');
}

$ownedPlaceholders = implode(',', array_fill(0, count($owned), '?'));
try {
    if ($action === 'activate') {
        $pdo->prepare("UPDATE produce_listings SET status = 'active' WHERE id IN ($ownedPlaceholders)")->execute($owned);
        flash_set('success', count($owned) . ' listing(s) activated.');
    } elseif ($action === 'deactivate') {
        $pdo->prepare("UPDATE produce_listings SET status = 'inactive' WHERE id IN ($ownedPlaceholders)")->execute($owned);
        flash_set('success', count($owned) . ' listing(s) deactivated.');
    } elseif ($action === 'delete') {
        $pdo->prepare("UPDATE produce_listings SET status = 'archived' WHERE id IN ($ownedPlaceholders)")->execute($owned);
        flash_set('success', count($owned) . ' listing(s) archived.');
    }
} catch (Throwable $e) {
    error_log('bulk_listing_action failed');
    flash_set('error', 'Action failed. Please try again.');
}
redirect_to('my_listings.php');
PHP;
file_put_contents('C:/Users/your4/Projects/animal-marketplace/public/bulk_listing_action.php', $content);
echo "written\n";
