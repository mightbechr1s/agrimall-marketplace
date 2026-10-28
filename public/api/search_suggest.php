<?php
require __DIR__ . '/../../src/bootstrap.php';
header('Content-Type: application/json');
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}
$q = mb_substr($q, 0, 60);
$suggestions = [];
try {
    $stmt = DB::pdo()->prepare(
        "SELECT name FROM produce_listings
          WHERE status = 'active'
            AND (MATCH(name, description) AGAINST(:fts IN NATURAL LANGUAGE MODE) OR name LIKE :like)
          GROUP BY name
          ORDER BY (SELECT COUNT(*) FROM saved_listings sl JOIN produce_listings pl ON pl.id = sl.listing_id WHERE pl.name = produce_listings.name) DESC,
                   name ASC
          LIMIT 8"
    );
    $stmt->execute([':fts' => $q, ':like' => '%' . $q . '%']);
    $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log('search_suggest failed: ' . $e->getMessage());
}
echo json_encode(['suggestions' => array_values(array_unique($suggestions))]);
