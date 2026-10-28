<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

require_login();
if (current_user_role() !== 'farmer') {
    redirect_to('/dashboard.php');
}

$locale = locale_current();
$l = function($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};
$navCopy = $copy;

$userId = current_user_id();
$pdo = DB::pdo();

// Pagination
$listingsPerPage = 12;
$listingsCountStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM produce_listings pl
     JOIN produce_categories pc ON pl.category_id = pc.id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     WHERE pl.farmer_id = :id AND pl.status <> \'archived\''
);
$listingsCountStmt->execute(['id' => $userId]);
$listingsTotal = (int) $listingsCountStmt->fetchColumn();
$listingsTotalQtyStmt = $pdo->prepare('SELECT COALESCE(SUM(available_quantity),0) FROM produce_listings WHERE farmer_id = :id AND status <> \'archived\'');
$listingsTotalQtyStmt->execute(['id' => $userId]);
$listingsTotalQty = (float) $listingsTotalQtyStmt->fetchColumn();
$listingsPages = max(1, (int) ceil($listingsTotal / $listingsPerPage));
$listingsPage = max(1, min($listingsPages, (int) ($_GET['listings_page'] ?? 1)));

$ls = $pdo->prepare(
    'SELECT pl.*, pc.name AS category_name, pc.slug AS category_slug, qu.symbol AS unit_symbol,
            fp.farm_name, fp.province,
            (SELECT li.image_path FROM listing_images li
              WHERE li.listing_id = pl.id AND li.is_primary = 1
              ORDER BY li.sort_order LIMIT 1) AS primary_image
     FROM produce_listings pl
     JOIN produce_categories pc ON pl.category_id = pc.id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     JOIN farmer_profiles fp ON fp.user_id = pl.farmer_id
     WHERE pl.farmer_id = :id AND pl.status <> \'archived\'
     ORDER BY pl.created_at DESC
     LIMIT :lim OFFSET :off'
);
$ls->bindValue(':id', $userId, PDO::PARAM_INT);
$ls->bindValue(':lim', $listingsPerPage, PDO::PARAM_INT);
$ls->bindValue(':off', ($listingsPage - 1) * $listingsPerPage, PDO::PARAM_INT);
$ls->execute();
$rawListings = $ls->fetchAll();

// Map to the listing-card partial's shape
$farmerListings = [];
foreach ($rawListings as $rl) {
    $farmerListings[] = [
        'id' => $rl['id'],
        'title' => $rl['name'],
        'price' => $rl['price_per_unit'],
        'unit' => $rl['unit_symbol'],
        'category' => $rl['category_name'],
        'image_path' => $rl['primary_image'],
        'status' => $rl['status'],
        'expiry_date' => $rl['expiry_date'],
        'farm_name' => $rl['farm_name'],
        'province' => $rl['province'],
    ];
}

$pageTitle = 'My Listings';
$activeNav = 'market';
$activePage = 'listings';
$sidebarShell = true;
$role = 'farmer';
require __DIR__ . '/partials/dashboard-layout.php';
?>

<section class="section" id="market">
<div class="container">
<div class="dash-section-header">
    <h1>My Listings</h1>
    <p>Manage your produce listings. <?= $listingsTotal ?> total, <?= rtrim(rtrim(number_format($listingsTotalQty, 2), '0'), '.') ?> units available.</p>
    <a href="create_listing.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Listing</a>
</div>

<?php if ($farmerListings): ?>
<div class="grid">
    <?php foreach ($farmerListings as $listing): ?>
        <?php $mode = 'farmer'; $showActions = true; ?>
        <?php include __DIR__ . '/partials/listing-card.php'; ?>
    <?php endforeach; ?>
</div>
    <?php
    $page = $listingsPage;
    $totalPages = $listingsPages;
    $pageParam = 'listings_page';
    $param = '';
    $labels = [
        'prev' => t($navCopy['ui'], 'pager_prev'),
        'next' => t($navCopy['ui'], 'pager_next'),
        'page' => t($navCopy['ui'], 'pager_page'),
        'of'   => t($navCopy['ui'], 'pager_of'),
    ];
    include __DIR__ . '/partials/pager.php';
    ?>
<?php else: ?>
<?php
$icon = '<i class="bi bi-basket3" aria-hidden="true"></i>';
$title = 'No listings yet';
$description = 'Create your first listing so buyers can find your produce.';
$action = ['href' => 'create_listing.php', 'label' => 'Create a listing', 'class' => 'btn btn-primary btn-sm'];
require __DIR__ . '/partials/empty-state.php';
?>
<?php endif; ?>
</div>
</section>

<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>