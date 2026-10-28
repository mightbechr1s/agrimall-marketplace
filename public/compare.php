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
$pdo = DB::pdo();
$stmt = $pdo->prepare(
    'SELECT pl.id, pl.name, pl.price_per_unit, pl.available_quantity, pl.minimum_order_qty,
            pl.description, fp.farm_name, fp.municipality_city, fp.province,
            qu.symbol, pc.name AS category_name,
            (SELECT li.image_path FROM listing_images li WHERE li.listing_id = pl.id AND li.is_primary = 1 ORDER BY li.sort_order LIMIT 1) AS primary_image
     FROM saved_listings sl
     JOIN produce_listings pl ON sl.listing_id = pl.id
     JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     JOIN produce_categories pc ON pl.category_id = pc.id
     WHERE sl.user_id = :u AND pl.status = \'active\'
     ORDER BY sl.created_at DESC'
);
$stmt->execute(['u' => $userId]);
$items = $stmt->fetchAll();
$activePage = 'saved';
$pageTitle = 'Compare Saved';
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1>Compare Saved Products</h1>
            <p>Side-by-side view of the products you saved.</p>
        </div>
        <a href="saved.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i> Back to saved</a>
        <?php if (count($items) < 2): ?>
            <div class="empty-state text-center">
                <i class="bi bi-columns empty-state-icon"></i>
                <h3 class="empty-state-title">Save at least 2 products to compare</h3>
                <p class="empty-state-description">Tap the heart on listings, then come back here.</p>
                <a class="btn btn-primary" href="browse.php">Browse Listings</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Attribute</th>
                            <?php foreach ($items as $it): ?>
                                <th class="text-center">
                                    <img src="<?= e(asset($it['primary_image'] ?: fallback_produce_image())) ?>" alt="" style="height:80px;object-fit:cover;" class="mb-2"><br>
                                    <?= e($it['name']) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><th>Price</th><?php foreach ($items as $it): ?><td class="text-center"><?= money($it['price_per_unit']) ?>/<?= e($it['symbol']) ?></td><?php endforeach; ?></tr>
                        <tr><th>Available</th><?php foreach ($items as $it): ?><td class="text-center"><?= e($it['available_quantity']) ?> <?= e($it['symbol']) ?></td><?php endforeach; ?></tr>
                        <tr><th>Min order</th><?php foreach ($items as $it): ?><td class="text-center"><?= e($it['minimum_order_qty']) ?> <?= e($it['symbol']) ?></td><?php endforeach; ?></tr>
                        <tr><th>Category</th><?php foreach ($items as $it): ?><td class="text-center"><?= e($it['category_name']) ?></td><?php endforeach; ?></tr>
                        <tr><th>Farm</th><?php foreach ($items as $it): ?><td class="text-center"><?= e($it['farm_name']) ?><br><small class="text-muted"><?= e($it['municipality_city']) ?></small></td><?php endforeach; ?></tr>
                        <tr><th>Action</th><?php foreach ($items as $it): ?>
                            <td class="text-center">
                                <form method="post" action="cart_add.php" class="mb-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="listing_id" value="<?= (int) $it['id'] ?>">
                                    <input type="hidden" name="quantity" value="<?= (float) $it['minimum_order_qty'] ?>">
                                    <input type="hidden" name="buy_now" value="1">
                                    <button type="submit" class="btn btn-primary btn-sm">Buy now</button>
                                </form>
                            </td>
                        <?php endforeach; ?></tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>