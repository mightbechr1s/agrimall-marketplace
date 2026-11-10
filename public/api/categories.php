<?php
require __DIR__ . '/_bootstrap.php';

$pdo = DB::pdo();
$categories = $pdo->query('SELECT id, name, slug FROM produce_categories WHERE is_active = 1 ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC);

api_ok(['categories' => $categories]);
