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
$address = mb_substr(trim((string) ($_POST['delivery_address'] ?? '')), 0, 500);
$barangay = mb_substr(trim((string) ($_POST['barangay'] ?? '')), 0, 100);
$municipality = mb_substr(trim((string) ($_POST['municipality'] ?? '')), 0, 100);
$province = mb_substr(trim((string) ($_POST['province'] ?? '')), 0, 100);

$pdo->prepare(
    "INSERT INTO buyer_profiles (user_id, full_name, delivery_address, barangay, municipality_city, province)
     VALUES (:uid, '', :addr, :brgy, :mun, :prov)
     ON DUPLICATE KEY UPDATE delivery_address = :addr2, barangay = :brgy2, municipality_city = :mun2, province = :prov2"
)->execute([
    'uid' => $userId,
    'addr' => $address,
    'brgy' => $barangay,
    'mun' => $municipality,
    'prov' => $province,
    'addr2' => $address,
    'brgy2' => $barangay,
    'mun2' => $municipality,
    'prov2' => $province,
]);

$fullName = '';
$stmt = $pdo->prepare('SELECT full_name FROM buyer_profiles WHERE user_id = :u');
$stmt->execute(['u' => $userId]);
$fullName = (string) $stmt->fetchColumn();
if ($fullName === '') {
    $pdo->prepare('UPDATE buyer_profiles SET full_name = :n WHERE user_id = :u')
        ->execute(['n' => 'User', 'u' => $userId]);
}

flash_set('success', 'Delivery address saved.');
$returnTo = (string) ($_POST['return_to'] ?? 'account.php');
if (!is_safe_path($returnTo)) $returnTo = 'account.php';
redirect_to($returnTo);
PHP;
file_put_contents('C:/Users/your4/Projects/animal-marketplace/public/save_address.php', $content);
echo "written\n";
