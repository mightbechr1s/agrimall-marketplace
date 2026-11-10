<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
$input = api_input();
$itemId = (int) ($input['item_id'] ?? 0);

if ($itemId <= 0) {
    api_error('Invalid item.');
}

$pdo = DB::pdo();
$pdo->prepare('DELETE FROM cart_items WHERE id = :id AND buyer_id = :buyer')
    ->execute(['id' => $itemId, 'buyer' => $user['id']]);

api_ok(['message' => 'Item removed.']);
