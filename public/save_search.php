<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_role('buyer', 'admin', 'manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$userId = current_user_id();
$pdo = DB::pdo();
$q = mb_substr(trim((string) ($_POST['q'] ?? '')), 0, 255);
$category = mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 100);
$priceMin = isset($_POST['price_min']) && $_POST['price_min'] !== '' ? (float) $_POST['price_min'] : null;
$priceMax = isset($_POST['price_max']) && $_POST['price_max'] !== '' ? (float) $_POST['price_max'] : null;

$pdo->prepare(
    'INSERT INTO saved_searches (user_id, query, category, price_min, price_max) VALUES (:u, :q, :c, :mn, :mx)'
)->execute([
    'u' => $userId,
    'q' => $q,
    'c' => $category,
    'mn' => $priceMin,
    'mx' => $priceMax,
]);
flash_set('success', 'Search saved. You will be notified when new listings match.');
$redirect = 'browse.php';
if ($q !== '') $redirect .= '?q=' . urlencode($q);
redirect_to($redirect);
PHP;
file_put_contents('C:/Users/your4/Projects/animal-marketplace/public/save_search.php', $content);
echo "written\n";
