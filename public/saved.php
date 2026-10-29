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

// Cleanup: drop saved entries whose listing is no longer active (sold out /
// archived / deleted) so the wishlist never shows dead products.
$pdo->prepare('DELETE sl FROM saved_listings sl
  LEFT JOIN produce_listings pl ON pl.id = sl.listing_id
  WHERE sl.user_id = :u AND (pl.id IS NULL OR pl.status <> \'active\')')
  ->execute(['u' => $userId]);

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
    'SELECT sl.listing_id, sl.price_at_save, pl.name, pl.price_per_unit, pl.description, fp.farm_name, qu.symbol,
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
    <div class="container" style="max-width: 1100px;">
        <div class="settings-header">
            <h1>Saved Listings</h1>
            <p>Your saved listings for later.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to dashboard</a>
        <a href="compare.php" class="btn btn-outline btn-sm mb-3 ms-2"><i class="bi bi-columns me-1" aria-hidden="true"></i> Compare saved</a>

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
                    <div class="card h-100">
                        <?php $img = $saved['primary_image'] ?: fallback_produce_image(); ?>
                        <img src="<?= e(asset($img)) ?>" class="card-img-top" alt="<?= e($saved['name']) ?>" style="height:160px;object-fit:cover;" onerror="this.src='<?= e(asset(fallback_produce_image())) ?>'">
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title mb-1"><?= e($saved['name']) ?></h6>
                            <p class="text-muted small mb-2"><?= e($saved['farm_name']) ?> · <?= e($saved['category_name']) ?></p>
                            <div class="fw-medium mb-3"><?= money($saved['price_per_unit']) ?> <small>/ <?= e($saved['symbol']) ?></small></div>
                            <?php if (!empty($saved['price_at_save']) && (float)$saved['price_at_save'] > (float)$saved['price_per_unit']): ?>
                                <div class="badge text-bg-success mb-2"><i class="bi bi-arrow-down"></i> Price dropped from <?= money($saved['price_at_save']) ?></div>
                            <?php endif; ?>
                            <div class="mt-auto d-flex gap-2">
                                <form method="post" action="cart_add.php" class="mb-0 flex-grow-1" data-buy-now="true">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="listing_id" value="<?= (int) $saved['listing_id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="buy_now" value="1">
                                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-lightning-charge-fill me-1"></i>Buy now</button>
                                </form>
                                <form method="post" action="saved_toggle.php" class="mb-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="listing_id" value="<?= (int) $saved['listing_id'] ?>">
                                    <input type="hidden" name="return_to" value="saved.php">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove" onclick="return confirm('Remove this saved listing?');"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
