<?php
require __DIR__ . '/../src/bootstrap.php';
$pdo = DB::pdo();
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE email = :e');
$stmt->execute(['e' => 'buyer1@seedtest.local']);
$h = $stmt->fetchColumn();
var_dump(password_verify('password123', $h));
echo "hash: " . $h . "\n";
