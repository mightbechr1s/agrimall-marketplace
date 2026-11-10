<?php
require __DIR__ . '/_bootstrap.php';

$pdo = DB::pdo();
$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$priceMin = isset($_GET['price_min']) ? (float) $_GET['price_min'] : 0;
$priceMax = isset($_GET['price_max']) ? (float) $_GET['price_max'] : 0;
$sort = in_array($_GET['sort'] ?? 'newest', ['newest','price_asc','price_desc','expiry'], true) ? (string) ($_GET['sort'] ?? 'newest') : 'newest';
$sortSql = ['newest'=>'published_at DESC','price_asc'=>'price_per_unit ASC','price_desc'=>'price_per_unit DESC','expiry'=>'expiry_date ASC'][$sort];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

// Build query
$where = "status = 'active'";
$bind = [];
if ($category !== '') {
    $where .= ' AND category_slug = :cat';
    $bind[':cat'] = $category;
}
if ($priceMin > 0) { $where .= ' AND price_per_unit >= :pmin'; $bind[':pmin'] = $priceMin; }
if ($priceMax > 0) { $where .= ' AND price_per_unit <= :pmax'; $bind[':pmax'] = $priceMax; }

// Search
if ($q !== '') {
    $sql = "SELECT pl.id FROM produce_listings pl JOIN produce_categories pc ON pc.id = pl.category_id
            WHERE pl.status = 'active' AND (MATCH(pl.name, pl.description) AGAINST(:fts IN NATURAL LANGUAGE MODE) OR pl.name LIKE :like)";
    $idsStmt = $pdo->prepare($sql);
    $idsStmt->execute([':fts' => $q, ':like' => '%' . $q . '%']);
    $matchIds = array_map('intval', $idsStmt->fetchAll(PDO::FETCH_COLUMN));
    $where = $matchIds ? 'id IN (' . implode(',', $matchIds) . ')' : '1 = 0';
}

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM v_active_listings WHERE $where");
foreach ($bind as $k => $v) $countStmt->bindValue($k, $v);
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);

$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("SELECT v_active_listings.*, (SELECT COUNT(*) FROM saved_listings sl WHERE sl.listing_id = v_active_listings.id) AS save_count FROM v_active_listings WHERE $where ORDER BY is_expired ASC, {$sortSql} LIMIT $perPage OFFSET :offset");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format listings for API
$formatted = array_map(function ($l) {
    return [
        'id' => (int) $l['id'],
        'name' => $l['name'],
        'price' => (float) $l['price_per_unit'],
        'unit' => $l['unit_symbol'],
        'category' => $l['category_name'],
        'category_slug' => $l['category_slug'],
        'farm_name' => $l['farm_name'],
        'province' => $l['province'],
        'image' => $l['primary_image'],
        'available_qty' => (float) $l['available_quantity'],
        'min_order' => (float) $l['minimum_order_qty'],
        'is_organic' => (bool) $l['is_organic'],
        'is_expired' => (bool) $l['is_expired'],
        'discount' => (int) $l['discount_percent'],
        'save_count' => (int) ($l['save_count'] ?? 0),
    ];
}, $listings);

api_ok([
    'total' => $total,
    'page' => $page,
    'total_pages' => $totalPages,
    'listings' => $formatted,
]);
