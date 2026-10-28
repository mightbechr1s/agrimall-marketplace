<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function ($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

$farmerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($farmerId <= 0) {
    http_response_code(404);
    exit('Farmer not found.');
}

$pdo = DB::pdo();
$fpStmt = $pdo->prepare(
    'SELECT u.id AS user_id, u.created_at, fp.farm_name, fp.farm_description, fp.contact_person,
            fp.contact_phone, fp.contact_email, fp.address, fp.barangay, fp.municipality_city,
            fp.province, fp.region, fp.verification_status, fp.verified_at
     FROM users u
     LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
     WHERE u.id = :id AND u.role = \'farmer\' AND u.status = \'active\''
);
$fpStmt->execute(['id' => $farmerId]);
$farmer = $fpStmt->fetch();
if (!$farmer || empty($farmer['farm_name'])) {
    http_response_code(404);
    exit('Farmer not found.');
}

// Pagination for this farmer's active listings.
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM produce_listings pl
     JOIN produce_categories pc ON pc.id = pl.category_id
     JOIN quantity_units qu ON qu.id = pl.unit_id
     WHERE pl.farmer_id = :id AND pl.status = 'active'
       AND pc.is_active = 1 AND qu.is_active = 1"
);
$countStmt->execute(['id' => $farmerId]);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    "SELECT pl.id, pl.name, pl.price_per_unit, pl.available_quantity, pl.minimum_order_qty,
            pl.is_organic, pl.harvest_date, pl.expiry_date, pl.published_at,
            qu.symbol AS unit_symbol, pc.name AS category_name,
            (SELECT li.image_path FROM listing_images li
              WHERE li.listing_id = pl.id AND li.is_primary = 1
              ORDER BY li.sort_order LIMIT 1) AS primary_image,
            (SELECT COUNT(*) FROM saved_listings sl WHERE sl.listing_id = pl.id) AS save_count
     FROM produce_listings pl
     JOIN produce_categories pc ON pc.id = pl.category_id
     JOIN quantity_units qu ON qu.id = pl.unit_id
     WHERE pl.farmer_id = :id AND pl.status = 'active'
       AND pc.is_active = 1 AND qu.is_active = 1
     ORDER BY pl.published_at DESC
     LIMIT :lim OFFSET :off"
);
$listStmt->bindValue(':id', $farmerId, PDO::PARAM_INT);
$listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$listStmt->execute();
$listings = $listStmt->fetchAll();

$completedStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE farmer_id = :id AND status = 'completed'");
$completedStmt->execute(['id' => $farmerId]);
$completedCount = (int) $completedStmt->fetchColumn();

$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_orders,
            AVG(CASE WHEN status = 'completed' AND completed_at IS NOT NULL
                     THEN TIMESTAMPDIFF(HOUR, created_at, completed_at) END) AS avg_hours
       FROM orders WHERE farmer_id = :id"
);
$statsStmt->execute(['id' => $farmerId]);
$stats = $statsStmt->fetch() ?: ['total_orders' => 0, 'avg_hours' => null];
$totalOrders = (int) $stats['total_orders'];
$avgHours = $stats['avg_hours'] !== null ? (float) $stats['avg_hours'] : null;

$pageTitle = $farmer['farm_name'];
$activeNav = 'browse';
$role = function_exists('current_user_role') ? current_user_role() : null;
$sidebarShell = (bool) current_user_id();
if ($sidebarShell) {
    $activePage = $role === 'buyer' ? 'browse' : 'market';
    require __DIR__ . '/partials/dashboard-layout.php';
} else {
    require __DIR__ . '/partials/header.php';
}
?>
<?php if (!$sidebarShell): ?>
<main id="main-content" class="container py-5">
<?php endif; ?>
    <div class="mb-3">
        <button type="button" class="btn btn-outline btn-sm" onclick="if (history.length > 1) history.back(); else location.href = 'browse.php';"><i class="bi bi-arrow-left me-1"></i>Back</button>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body p-3">
                    <h2 class="h4 mb-2"><?= e($farmer['farm_name']) ?></h2>
                    <?php if ($farmer['verification_status'] === 'approved'): ?>
                        <span class="badge text-bg-success mb-2"><i class="bi bi-patch-check-fill"></i> Verified farmer</span>
                    <?php endif; ?>
                    <p class="text-muted small mb-2"><i class="bi bi-geo-alt-fill"></i> <?= e($farmer['barangay']) ?>, <?= e($farmer['municipality_city']) ?>, <?= e($farmer['province']) ?></p>
                    <?php if (!empty($farmer['farm_description'])): ?>
                        <p class="small mb-2"><?= e($farmer['farm_description']) ?></p>
                    <?php endif; ?>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li><i class="bi bi-basket3 me-1"></i><?= $total ?> active listing<?= $total === 1 ? '' : 's' ?></li>
                        <li><i class="bi bi-check-circle me-1"></i><?= $completedCount ?> completed order<?= $completedCount === 1 ? '' : 's' ?></li>
                        <?php if ($totalOrders > 0): ?>
                            <li><i class="bi bi-graph-up me-1"></i><?= $completedCount ?> of <?= $totalOrders ?> orders completed<?= $avgHours !== null ? ' · avg ' . round($avgHours) . 'h fulfillment' : '' ?></li>
                        <?php endif; ?>
                        <?php if ($farmer['verified_at']): ?>
                            <li><i class="bi bi-calendar-check me-1"></i>Verified <?= e(date('M Y', strtotime($farmer['verified_at']))) ?></li>
                        <?php endif; ?>
                    </ul>
                    <div class="mt-3 pt-2 border-top">
                        <div class="small fw-semibold mb-1">Contact this farmer</div>
                        <?php if (!empty($farmer['contact_phone'])): ?>
                            <div class="small"><i class="bi bi-telephone me-1"></i><a href="tel:<?= e($farmer['contact_phone']) ?>"><?= e($farmer['contact_phone']) ?></a></div>
                        <?php endif; ?>
                        <?php if (!empty($farmer['contact_email'])): ?>
                            <div class="small"><i class="bi bi-envelope me-1"></i><a href="mailto:<?= e($farmer['contact_email']) ?>"><?= e($farmer['contact_email']) ?></a></div>
                        <?php endif; ?>
                        <?php if (!empty($farmer['facebook_url'])): ?>
                            <div class="small"><i class="bi bi-facebook me-1"></i><a href="<?= e($farmer['facebook_url']) ?>" target="_blank" rel="noopener">Facebook</a></div>
                        <?php endif; ?>
                        <?php if (!empty($farmer['instagram_url'])): ?>
                            <div class="small"><i class="bi bi-instagram me-1"></i><a href="<?= e($farmer['instagram_url']) ?>" target="_blank" rel="noopener">Instagram</a></div>
                        <?php endif; ?>
                        <?php if (!empty($farmer['tiktok_url'])): ?>
                            <div class="small"><i class="bi bi-tiktok me-1"></i><a href="<?= e($farmer['tiktok_url']) ?>" target="_blank" rel="noopener">TikTok</a></div>
                        <?php endif; ?>
                        <?php if (!empty($farmer['website_url'])): ?>
                            <div class="small"><i class="bi bi-globe me-1"></i><a href="<?= e($farmer['website_url']) ?>" target="_blank" rel="noopener">Website</a></div>
                        <?php endif; ?>
                        <?php if (empty($farmer['contact_phone']) && empty($farmer['contact_email']) && empty($farmer['facebook_url']) && empty($farmer['instagram_url']) && empty($farmer['tiktok_url']) && empty($farmer['website_url'])): ?>
                            <div class="text-muted small">No contact details shared yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <h3 class="h5 mb-3">Products from <?= e($farmer['farm_name']) ?> (<?= $total ?>)</h3>
            <?php if (!$listings): ?>
                <div class="text-muted">No active listings right now.</div>
            <?php else: ?>
                <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                    <?php foreach ($listings as $it): ?>
                        <?php
                            $img = $it['primary_image'] ?: fallback_produce_image();
                            $isSaved = false;
                            if (current_user_id() && current_user_role() === 'buyer') {
                                try {
                                    $sv = DB::pdo()->prepare('SELECT 1 FROM saved_listings WHERE user_id = :u AND listing_id = :l');
                                    $sv->execute(['u' => current_user_id(), 'l' => $it['id']]);
                                    $isSaved = (bool) $sv->fetchColumn();
                                } catch (Exception $e) {}
                            }
                            $listing = array_merge($it, ['is_saved' => $isSaved, 'title' => $it['name'], 'price' => $it['price_per_unit'], 'unit' => $it['unit_symbol'], 'category' => $it['category_name'], 'farmer_name' => $farmer['farm_name'], 'province' => $farmer['province'], 'farmer_id' => $farmerId, 'farmer_slug' => '', 'image_path' => $img, 'is_fresh' => false, 'is_stale' => false, 'status' => 'active']);
                            $mode = 'grid';
                            $showActions = true;
                            include __DIR__ . '/partials/listing-card.php';
                        ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <?php
                    $pageParam = 'page';
                    $param = 'id=' . (int) $farmerId;
                    $labels = ['prev' => 'Previous', 'next' => 'Next', 'page' => 'Page', 'of' => 'of'];
                    include __DIR__ . '/partials/pager.php';
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php if (!$sidebarShell): ?>
</main>
<?php endif; ?>
<?php require $sidebarShell ? __DIR__ . '/partials/dashboard-footer.php' : __DIR__ . '/partials/footer.php'; ?>