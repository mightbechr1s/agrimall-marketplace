<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function ($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

require_login();
$userId = current_user_id();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$pdo = DB::pdo();

$savedWhere = 'FROM saved_listings sl
     JOIN produce_listings pl ON sl.listing_id = pl.id
     JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     JOIN produce_categories pc ON pl.category_id = pc.id
     WHERE sl.user_id = :user AND pl.status = \'active\'';
$countStmt = $pdo->prepare('SELECT COUNT(*) ' . $savedWhere);
$countStmt->execute(['user' => $userId]);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = max(1, min($pages, $page));
$savedStmt = $pdo->prepare(
    'SELECT sl.listing_id, pl.name, pl.price_per_unit, pl.description, fp.farm_name, qu.symbol,
            (SELECT li.image_path FROM listing_images li
              WHERE li.listing_id = pl.id AND li.is_primary = 1
              ORDER BY li.sort_order LIMIT 1) AS primary_image,
            pc.name AS category_name, sl.created_at ' . $savedWhere . '
     ORDER BY sl.created_at DESC
     LIMIT :lim OFFSET :off'
);
$savedStmt->bindValue(':user', $userId, PDO::PARAM_INT);
$savedStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$savedStmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$savedStmt->execute();
$savedListings = $savedStmt->fetchAll();

$activePage = 'saved';
$pageTitle = 'Saved Listings';
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1>Saved Listings</h1>
            <p>Your saved listings for later.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to dashboard</a>

        <?php if (!$savedListings): ?>
            <div class="empty-state text-center">
                <i class="bi bi-heart empty-state-icon" aria-hidden="true"></i>
                <h3 class="empty-state-title">Nothing saved yet</h3>
                <p class="empty-state-description">Tap the heart on a listing to keep it here for later.</p>
                <a class="btn btn-primary" href="browse.php">Browse Listings</a>
            </div>
        <?php else: ?>
            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                <?php foreach ($savedListings as $saved): ?>
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><?= e($saved['name']) ?></h6>
                            <p class="text-muted small mb-0"><?= e($saved['farm_name']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
