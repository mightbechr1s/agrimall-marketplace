<?php
// Seed the throwaway test DB for HTTP end-to-end tests.
declare(strict_types=1);

$repo = dirname(__DIR__);
$config = require $repo . '/config/config.php';
require $repo . '/src/DB.php';
DB::init(['host' => '127.0.0.1', 'port' => 3306, 'name' => 'animal_marketplace_test',
          'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4']);
$pdo = DB::pdo();

function mkUser(PDO $pdo, string $email, string $role, string $pass = 'password123'): int {
    $pdo->prepare('INSERT INTO users (email, password_hash, role, status) VALUES (?,?,?,"active")')
        ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $role]);
    return (int) $pdo->lastInsertId();
}
function mkBuyer(PDO $pdo, int $uid, string $name): void {
    $pdo->prepare('INSERT INTO buyer_profiles (user_id, full_name, contact_phone, delivery_address, preferred_pickup)
                   VALUES (?,?,?,?,0)')->execute([$uid, $name, '09171234567', '12 Katipunan St, QC']);
}
function mkFarmer(PDO $pdo, int $uid, string $farm, string $contact, string $status = 'approved'): void {
    $pdo->prepare('INSERT INTO farmer_profiles
        (user_id, farm_name, farm_description, contact_person, contact_phone, contact_email,
         address, barangay, municipality_city, province, region, valid_id_type, valid_id_number, verification_status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$uid, $farm, 'desc', $contact, '09179876543', 'f@x.test', '5 Rural Rd',
                   'San Isidro', 'Bayambang', 'Pampanga', 'Pangasinan', 'national_id', 'ID-9', $status]);
}

$cat  = (int) $pdo->query('SELECT id FROM produce_categories LIMIT 1')->fetchColumn();
$unit = (int) $pdo->query('SELECT id FROM quantity_units LIMIT 1')->fetchColumn();

$buyer      = mkUser($pdo, 'http-buyer@x.test', 'buyer');   mkBuyer($pdo, $buyer, 'HTTP Buyer');
$farmer     = mkUser($pdo, 'http-farmer@x.test', 'farmer'); mkFarmer($pdo, $farmer, 'HTTP Farm', 'H farmer');
$resetOnly  = mkUser($pdo, 'http-reset@x.test', 'buyer');   mkBuyer($pdo, $resetOnly, 'Reset Buyer');
$openOrder  = mkUser($pdo, 'http-open@x.test', 'buyer');    mkBuyer($pdo, $openOrder, 'Open Buyer');
$blocked    = mkUser($pdo, 'http-block@x.test', 'buyer');   mkBuyer($pdo, $blocked, 'Blocked Buyer');
$admin      = mkUser($pdo, 'http-admin@x.test', 'admin');

// Pending farmer applications for the admin verification flow.
$approveMe  = mkUser($pdo, 'http-pending@x.test', 'farmer');
mkFarmer($pdo, $approveMe, 'Pending Farm', 'P farmer', 'pending');
$pdo->prepare("UPDATE users SET status = 'pending_verification' WHERE id = ?")->execute([$approveMe]);
$rejectMe   = mkUser($pdo, 'http-reject@x.test', 'farmer');
mkFarmer($pdo, $rejectMe, 'Reject Farm', 'R farmer', 'pending');
$pdo->prepare("UPDATE users SET status = 'pending_verification' WHERE id = ?")->execute([$rejectMe]);

$pdo->prepare('INSERT INTO produce_listings (farmer_id, category_id, unit_id, name, price_per_unit, status)
               VALUES (?,?,?,?,?,"active")')->execute([$farmer, $cat, $unit, 'HTTP Produce', 25.00]);
$listing = (int) $pdo->lastInsertId();

// A PENDING order blocks the open-order buyer from deleting.
$pdo->prepare('INSERT INTO orders (order_number, buyer_id, farmer_id, listing_id, quantity, unit_price, status)
               VALUES (?,?,?,?,2.0000,25.00,"pending")')->execute(['ORD-HTTP1', $openOrder, $farmer, $listing]);

// An unreleased retention hold blocks the other buyer.
$pdo->prepare("INSERT INTO retention_holds (entity_type, entity_id, admin_id, reason) VALUES ('user',?,?,'test hold')")
    ->execute([$blocked, $admin]);

echo json_encode([
    'buyer' => $buyer, 'farmer' => $farmer, 'resetOnly' => $resetOnly,
    'openOrder' => $openOrder, 'blocked' => $blocked, 'admin' => $admin, 'listing' => $listing,
    'approveMe' => $approveMe, 'rejectMe' => $rejectMe,
]) . "\n";
