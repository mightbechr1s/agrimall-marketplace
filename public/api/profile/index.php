<?php
require __DIR__ . '/../_bootstrap.php';

$pdo = DB::pdo();
$user = api_require_auth();
$userId = $user['id'];
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $profile = [];
    if (in_array($role, ['buyer', 'admin', 'manager'], true)) {
        $stmt = $pdo->prepare('SELECT full_name, contact_phone, delivery_address, barangay, municipality_city, province, region FROM buyer_profiles WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    if ($role === 'farmer') {
        $stmt = $pdo->prepare('SELECT farm_name, contact_person, contact_phone, contact_email, address, barangay, municipality_city, province, region, verification_status FROM farmer_profiles WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Notifications
    $notifStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL');
    $notifStmt->execute(['u' => $userId]);
    $unread = (int) $notifStmt->fetchColumn();

    api_ok([
        'user' => ['id' => $userId, 'role' => $role],
        'profile' => $profile,
        'unread_notifications' => $unread,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = api_input();
    if (in_array($role, ['buyer', 'admin', 'manager'], true)) {
        $fullName = mb_substr(trim((string) ($input['full_name'] ?? '')), 0, 100);
        $phone = mb_substr(trim((string) ($input['contact_phone'] ?? '')), 0, 20);
        $address = mb_substr(trim((string) ($input['delivery_address'] ?? '')), 0, 500);
        $pdo->prepare(
            "INSERT INTO buyer_profiles (user_id, full_name, contact_phone, delivery_address) VALUES (:uid, :name, :phone, :addr)
             ON DUPLICATE KEY UPDATE full_name = :name2, contact_phone = :phone2, delivery_address = :addr2"
        )->execute([
            'uid' => $userId, 'name' => $fullName, 'phone' => $phone, 'addr' => $address,
            'name2' => $fullName, 'phone2' => $phone, 'addr2' => $address,
        ]);
    }
    api_ok(['message' => 'Profile updated.']);
}

api_error('Method not allowed.', 405);
