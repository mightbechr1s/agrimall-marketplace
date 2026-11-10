<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

$pageTitle = 'Marketplace';
$activeNav = 'browse';
$locale = locale_current();
$l = function($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};
$q = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
// Bound the search term to the column width so an oversized query cannot
// drive a huge LIKE scan or be stored in error/log output.
$q = mb_substr($q, 0, FIELD_LIMITS['q']);
$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$categoryFilter = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $categoryFilter));
$dealsFilter = !empty($_GET['deals']);
$trendingFilter = !empty($_GET['trending']);
$priceMin = isset($_GET['price_min']) ? (float) $_GET['price_min'] : 0.0;
$priceMax = isset($_GET['price_max']) ? (float) $_GET['price_max'] : 0.0;
$sort = in_array(($_GET['sort'] ?? 'newest'), ['newest','price_asc','price_desc','expiry'], true) ? (string) ($_GET['sort'] ?? 'newest') : 'newest';
$sortSql = ['newest'=>'published_at DESC','price_asc'=>'price_per_unit ASC','price_desc'=>'price_per_unit DESC','expiry'=>'expiry_date ASC'][$sort];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Query runs before the markup so the header can print the real result count.
$listings = [];
$total = 0;
$totalPages = 0;
// A DB outage and an empty marketplace are different states; do not share one flag.
$listingsFailed = false;
try {
    $hasSearch = $q !== '';
    // Search path: resolve matching ids on the base table first (one indexed
    // scan using ft_listing_search + a substring fallback for prefixes the
    // tokenizer misses) and let the 5-join view drive only off `id IN (...)`.
    // LIKE '%...%' over the view full-scanned every join per keystroke.
    if ($hasSearch) {
        $sql = "SELECT pl.id FROM produce_listings pl";
        if ($categoryFilter !== '') {
            $sql .= " JOIN produce_categories pc ON pc.id = pl.category_id";
        }
        $sql .= " WHERE pl.status = 'active'";
        if ($categoryFilter !== '') {
            $sql .= " AND pc.slug = :cat";
        }
        if ($dealsFilter) {
            $sql .= " AND pl.discount_percent > 0 AND (pl.expiry_date IS NULL OR pl.expiry_date = '' OR pl.expiry_date >= CURDATE())";
        }
        if ($trendingFilter) {
            $sql .= " AND (SELECT COUNT(*) FROM saved_listings sl WHERE sl.listing_id = pl.id) >= 3";
        }
        $sql .= " AND (MATCH(pl.name, pl.description) AGAINST(:fts IN NATURAL LANGUAGE MODE) OR pl.name LIKE :like)";
        $idsStmt = DB::pdo()->prepare($sql);
        $params = [':fts' => $q, ':like' => '%' . $q . '%'];
        if ($categoryFilter !== '') $params[':cat'] = $categoryFilter;
        $idsStmt->execute($params);
        $matchIds = array_map('intval', $idsStmt->fetchAll(PDO::FETCH_COLUMN));
        $where = $matchIds
            ? 'id IN (' . implode(',', $matchIds) . ')'
            : '1 = 0';   // no hits: count and grid both come up empty
    } else {
        $where = "status = 'active'";
    }
    // Category filter applies to the whole catalogue, not just the
    // current page (was a client-side toggle that only hid cards).
    $bind = [];
    if ($categoryFilter !== '') {
        $where .= ' AND category_slug = :cat';
        $bind[':cat'] = $categoryFilter;
    }
    if ($dealsFilter) {
        $where .= ' AND discount_percent > 0 AND is_expired = 0';
    }
    if ($trendingFilter) {
        $where .= ' AND (SELECT COUNT(*) FROM saved_listings sl WHERE sl.listing_id = v_active_listings.id) >= 3';
    }
    if ($priceMin > 0) { $where .= ' AND price_per_unit >= :pmin'; $bind[':pmin'] = $priceMin; }
    if ($priceMax > 0) { $where .= ' AND price_per_unit <= :pmax'; $bind[':pmax'] = $priceMax; }

    // Count first: an out-of-range ?page= (bookmark, edited URL) is pulled back
    // onto the last page before it can become an OFFSET and show an empty grid.
    $countStmt = DB::pdo()->prepare("SELECT COUNT(*) FROM v_active_listings WHERE $where");
    foreach ($bind as $k => $v) $countStmt->bindValue($k, $v);
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($total / $perPage);
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    $stmt = DB::pdo()->prepare("SELECT v_active_listings.*, (SELECT COUNT(*) FROM saved_listings sl WHERE sl.listing_id = v_active_listings.id) AS save_count, (SELECT COUNT(*) FROM orders o WHERE o.listing_id = v_active_listings.id AND o.status = 'completed' AND o.completed_at >= (NOW() - INTERVAL 7 DAY)) AS sold_week FROM v_active_listings WHERE $where ORDER BY CASE WHEN discount_percent > 0 THEN 0 ELSE 1 END, CASE WHEN (SELECT COUNT(*) FROM saved_listings sl2 WHERE sl2.listing_id = v_active_listings.id) >= 3 THEN 0 ELSE 1 END, is_expired ASC, {$sortSql} LIMIT $perPage OFFSET :offset");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $listings = $stmt->fetchAll();

    // Map pins: listings whose farmer has geocoded coordinates.
    $mapPins = [];
    try {
        $mapPins = DB::pdo()->query(
            "SELECT pl.id, pl.name, pl.price_per_unit, pl.available_quantity, pl.unit_symbol,
                    fp.farm_name, fp.latitude, fp.longitude, fp.municipality_city, fp.province
               FROM produce_listings pl
               JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
              WHERE pl.status = 'active' AND fp.latitude IS NOT NULL AND fp.longitude IS NOT NULL
              LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $mapPins = [];
    }
} catch (Exception $e) {
    $listingsFailed = true;
    error_log('browse: listing query failed: ' . $e->getMessage());
}
$totalPages = $listingsFailed ? 0 : $totalPages;

// Category label formatter
function formatCatLabel($catName) {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $catName), '-'));
    $labels = [
        'vegetables' => 'Vegetables',
        'leafy-greens' => 'Leafy Greens',
        'fruits' => 'Fruits',
        'meat' => 'Meat',
        'meat-poultry' => 'Meat & Poultry',
        'poultry' => 'Poultry',
        'seafood' => 'Seafood',
        'fish' => 'Fish',
        'fish-seafood' => 'Fish & Seafood',
        'grains' => 'Grains',
        'grains-legumes' => 'Grains & Legumes',
        'dairy' => 'Dairy',
        'livestock' => 'Livestock',
        'livestock-dairy' => 'Dairy',
        'root-crops' => 'Root Crops',
        'herbs-spices' => 'Herbs & Spices',
        'eggs-poultry' => 'Eggs & Poultry',
    ];
    return $labels[$slug] ?? ucwords(str_replace('-', ' ', $slug));
}

// Saved listing ids for the signed-in buyer (heart buttons)
$savedIds = [];
if (current_user_id() && in_array(current_user_role(), ['buyer', 'admin', 'manager'], true)) {
    try {
        $savedStmt = DB::pdo()->prepare('SELECT listing_id FROM saved_listings WHERE user_id = :id');
        $savedStmt->execute(['id' => current_user_id()]);
        $savedIds = $savedStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Decorative query: degrade rather than break the page, but do not
        // swallow it silently -- a failure here is invisible otherwise.
        error_log('browse: saved-listings lookup failed: ' . $e->getMessage());
    }
}
$role = function_exists('current_user_role') ? current_user_role() : null;
$sidebarShell = (bool) current_user_id();
if ($sidebarShell) {
    $activePage = $role === 'buyer' ? 'browse' : (in_array($role, ['admin', 'manager'], true) ? 'goods' : 'market');
    require __DIR__ . '/partials/dashboard-layout.php';
} else {
    require __DIR__ . '/partials/header.php';
}
?>

<!-- Marketplace -->
<?php if (!$sidebarShell): ?>
<main id="main-content" class="flex-grow-1">
<?php endif; ?>
<section class="section" id="market">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="kicker"><?php echo $l($copy['preview'], 'section_title'); ?></span>
      <h1>What's fresh today</h1>
      <p><?php echo $l($copy['preview'], 'intro'); ?></p>
    </div>

    <?php if (current_user_id() && current_user_role() === 'farmer'): ?>
    <div class="d-flex gap-3 mb-3">
      <a href="my_listings.php" class="btn btn-outline btn-sm"><i class="bi bi-basket3 me-1"></i>My Listings</a>
      <a href="create_listing.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Listing</a>
    </div>
    <?php endif; ?>

    <div class="browse-toolbar">
    <form class="search-form" method="get" action="browse.php">
      <?php if (isset($_GET['lang'])): ?>
        <input type="hidden" name="lang" value="<?php echo htmlspecialchars($_GET['lang']); ?>">
      <?php endif; ?>
      <label class="visually-hidden" for="market-search"><?= e($l($copy['search'], 'label')) ?></label>
      <input id="market-search" type="search" name="q" list="search-suggestions" placeholder="<?= e($l($copy['search'], 'placeholder')) ?>" value="<?php echo htmlspecialchars($q); ?>">
      <datalist id="search-suggestions"></datalist>
      <button type="submit" class="search-form__submit" aria-label="<?= e($l($copy['search'], 'label')) ?>"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>

    <!-- Category filters (primary) -->
    <div class="browse-filter-group">
      <span class="browse-filter-group__label">Category</span>
      <div class="filters" data-reveal>
      <?php
      $categories = [];
      $useFallback = false;
      try {
        $stmt = DB::pdo()->query('SELECT name FROM produce_categories WHERE is_active=1 ORDER BY sort_order');
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
      } catch (Exception $e) {
        $useFallback = true;
      }
      if ($useFallback || empty($categories)) {
        $categories = ['fruits', 'livestock-dairy', 'poultry', 'seafood', 'vegetables'];
        sort($categories);
      }
      $filterCounts = ['all' => 0, 'deals' => 0, 'trending' => 0];
      $catCounts = [];
      try {
        $filterCounts['all'] = (int) DB::pdo()->query("SELECT COUNT(*) FROM v_active_listings")->fetchColumn();
        $filterCounts['deals'] = (int) DB::pdo()->query("SELECT COUNT(*) FROM v_active_listings WHERE discount_percent > 0 AND is_expired = 0")->fetchColumn();
        $filterCounts['trending'] = (int) DB::pdo()->query("SELECT COUNT(*) FROM v_active_listings WHERE (SELECT COUNT(*) FROM saved_listings sl WHERE sl.listing_id = v_active_listings.id) >= 3")->fetchColumn();
        foreach (DB::pdo()->query("SELECT category_slug, COUNT(*) c FROM v_active_listings GROUP BY category_slug")->fetchAll(PDO::FETCH_KEY_PAIR) as $slug => $c) {
            $catCounts[strtolower((string)$slug)] = (int) $c;
        }
      } catch (Exception $e) { }
      ?>

      <button class="filter-pill <?= $categoryFilter === '' && !$dealsFilter && !$trendingFilter ? 'is-active' : '' ?>" type="button" data-filter="all" data-filter-url="browse.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">All (<?= (int) $filterCounts['all'] ?>)</button>
      <button class="filter-pill <?= $dealsFilter ? 'is-active' : '' ?>" type="button" data-filter="deals" data-filter-url="browse.php?deals=1<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">Deals (<?= (int) $filterCounts['deals'] ?>)</button>
      <button class="filter-pill <?= $trendingFilter ? 'is-active' : '' ?>" type="button" data-filter="trending" data-filter-url="browse.php?trending=1<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">Trending (<?= (int) $filterCounts['trending'] ?>)</button>
      <?php foreach ($categories as $cat): ?>
        <?php $catSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($cat))); ?>
        <?php $catUrl = 'browse.php?category=' . urlencode($catSlug) . ($q !== '' ? '&q=' . urlencode($q) : ''); ?>
        <button class="filter-pill <?= $categoryFilter === $catSlug ? 'is-active' : '' ?>" type="button" data-filter="<?php echo strtolower($cat); ?>" data-filter-url="<?= e($catUrl) ?>"><?php echo htmlspecialchars(formatCatLabel($cat)); ?> (<?= (int) ($catCounts[$catSlug] ?? 0) ?>)</button>
      <?php endforeach; ?>
      </div>
    </div>

    </div>

    <!-- Advanced filters (collapsible) -->
    <?php $hasAdvanced = $priceMin > 0 || $priceMax > 0 || $sort !== 'newest'; ?>
    <details class="browse-advanced" <?= $hasAdvanced ? 'open' : '' ?>>
      <summary class="browse-advanced__summary">
        <i class="bi bi-sliders me-1" aria-hidden="true"></i>Price &amp; sort
        <?php if ($hasAdvanced): ?><span class="browse-advanced__badge">active</span><?php endif; ?>
      </summary>
      <form method="get" action="browse.php" class="browse-filters">
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <?php if ($categoryFilter !== ''): ?><input type="hidden" name="category" value="<?= e($categoryFilter) ?>"><?php endif; ?>
        <?php if ($dealsFilter): ?><input type="hidden" name="deals" value="1"><?php endif; ?>
        <?php if ($trendingFilter): ?><input type="hidden" name="trending" value="1"><?php endif; ?>
        <div class="browse-filters__field">
          <label for="priceMin">Min price</label>
          <input type="number" step="0.01" min="0" id="priceMin" name="price_min" value="<?= $priceMin > 0 ? e($priceMin) : '' ?>">
        </div>
        <div class="browse-filters__field">
          <label for="priceMax">Max price</label>
          <input type="number" step="0.01" min="0" id="priceMax" name="price_max" value="<?= $priceMax > 0 ? e($priceMax) : '' ?>">
        </div>
        <div class="browse-filters__field">
          <label for="sort">Sort by</label>
          <select id="sort" name="sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Lowest price</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Highest price</option>
            <option value="expiry" <?= $sort === 'expiry' ? 'selected' : '' ?>>Expiring soon</option>
          </select>
        </div>
        <div class="browse-filters__actions">
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
          <a href="browse.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>
    </details>

    <div id="recently-viewed" class="mb-3" style="display:none;">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Recently viewed</h2>
      </div>
      <div class="d-flex gap-2 overflow-auto pb-2" id="recently-viewed-list"></div>
    </div>
    <?php
      $activeFilters = [];
      if ($q !== '') $activeFilters[] = ['label' => 'Search: ' . $q, 'href' => http_build_query(array_merge($_GET, ['q' => '']))];
      if ($categoryFilter !== '') $activeFilters[] = ['label' => 'Category: ' . formatCatLabel($categoryFilter), 'href' => http_build_query(array_merge($_GET, ['category' => '']))];
      if ($dealsFilter) $activeFilters[] = ['label' => 'Deals only', 'href' => http_build_query(array_merge($_GET, ['deals' => '']))];
      if ($trendingFilter) $activeFilters[] = ['label' => 'Trending', 'href' => http_build_query(array_merge($_GET, ['trending' => '']))];
      if ($priceMin > 0 || $priceMax > 0) $activeFilters[] = ['label' => 'Price ' . ($priceMin > 0 ? money($priceMin) : 'any') . ' – ' . ($priceMax > 0 ? money($priceMax) : 'any'), 'href' => http_build_query(array_merge($_GET, ['price_min' => '', 'price_max' => '']))];
      $sortOptions = ['newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'expiry' => 'Expiring soon'];
    ?>
    <div class="browse-results-head" data-reveal>
      <p class="browse-results-count" role="status">
        <strong><?= (int) $total ?></strong> <?= (int) $total === 1 ? 'listing' : 'listings' ?><?= $q !== '' ? ' for "' . e($q) . '"' : '' ?>
      </p>
      <?php if (current_user_id() && in_array(current_user_role(), ['buyer', 'admin', 'manager'], true)): ?>
      <form method="post" action="save_search.php" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <input type="hidden" name="category" value="<?= e($categoryFilter) ?>">
        <input type="hidden" name="price_min" value="<?= $priceMin > 0 ? e($priceMin) : '' ?>">
        <input type="hidden" name="price_max" value="<?= $priceMax > 0 ? e($priceMax) : '' ?>">
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-bookmark-plus me-1"></i>Save search</button>
      </form>
      <?php endif; ?>
    </div>
    <?php if ($activeFilters): ?>
    <div class="browse-active-filters" data-reveal>
      <?php foreach ($activeFilters as $af): ?>
        <a href="browse.php?<?= e($af['href']) ?>" class="filter-chip">
          <?= e($af['label']) ?> <i class="bi bi-x" aria-hidden="true"></i>
        </a>
      <?php endforeach; ?>
      <a href="browse.php" class="browse-active-filters__clear">Clear all</a>
    </div>
    <?php endif; ?>

    <div class="grid" id="product-grid" data-reveal="stagger">
      <?php if ($listings): ?>
        <?php foreach ($listings as $item): ?>
          <article class="card" data-reveal="stagger" data-listing-url="listing.php?id=<?php echo (int) $item['id']; ?>" data-category="<?php echo e(strtolower($item['category_name'] ?? '')); ?>">
            <div class="swipe-reveal swipe-reveal--left" aria-hidden="true"><i class="bi bi-star"></i></div>
            <div class="swipe-reveal swipe-reveal--right" aria-hidden="true"><i class="bi bi-basket3"></i></div>
            <?php
              $categorySlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $item['category_name'] ?? ''), '-'));
              $nameSlug = strtolower($item['name'] ?? '');
              $fallbackPath = fallback_produce_image();
              $imagePath = $item['primary_image'] ?: $fallbackPath;
            ?>
            <div class="thumb" style="background: var(--canvas);">
              <img src="<?= e(asset($imagePath)) ?>" onerror="this.onerror=null;this.src='<?= e(asset($fallbackPath)) ?>'" alt="<?php echo e($item['name']); ?>" loading="lazy" width="400" height="400">
            </div>
            <div class="body">
              <div class="badges-top">
                <?php if (listing_is_expired($item)): ?>
                  <span class="badge text-bg-warning" data-testid="expired-badge"><?= $l($copy['listing'], 'expired_badge') ?></span>
                <?php endif; ?>
                <?php if (!empty($item['discount_percent']) && (int) $item['discount_percent'] > 0 && !listing_is_expired($item)): ?>
                  <span class="badge" style="background:#7c3aed;color:#fff;"><i class="bi bi-tag-fill"></i> <?= (int) $item['discount_percent'] ?>% OFF</span>
                <?php endif; ?>
                <?php if (!empty($item['save_count']) && (int) $item['save_count'] >= 3): ?>
                  <span class="badge" style="background:#f59e0b;color:#111;font-size:12px;padding:4px 8px;"><i class="bi bi-fire"></i> Trending</span>
                <?php elseif (!empty($item['save_count']) && (int) $item['save_count'] > 0): ?>
                  <span class="badge" style="background:#dc3545;color:#fff;"><i class="bi bi-heart-fill"></i> <?= (int) $item['save_count'] ?> saved</span>
                <?php endif; ?>
                <?php if (!empty($item['sold_week']) && (int) $item['sold_week'] > 0): ?>
                  <span class="badge" style="background:#16a34a;color:#fff;"><i class="bi bi-bag-check"></i> <?= (int) $item['sold_week'] ?> sold this week</span>
                <?php endif; ?>
                <?php if ((float)($item['available_quantity'] ?? 0) > 0 && (float)$item['available_quantity'] <= max(5, (float)($item['minimum_order_qty'] ?? 1) * 2)): ?>
                  <span class="badge" style="background:#f97316;color:#fff;"><i class="bi bi-exclamation-triangle"></i> Only <?= rtrim(rtrim(number_format((float)$item['available_quantity'], 2), '0'), '.') ?> left</span>
                <?php endif; ?>
                <?php if ((int) ($item['is_organic'] ?? 0) === 1): ?>
                  <span class="badge text-bg-success"><i class="bi bi-leaf"></i> Organic</span>
                <?php endif; ?>
                <?php
                  $harvest = $item['harvest_date'] ?? null;
                  $freshLabel = null;
                  if ($harvest) {
                    $days = (int) floor((time() - strtotime($harvest)) / 86400);
                    $freshLabel = $days <= 0 ? 'Picked today' : ($days === 1 ? 'Picked 1 day ago' : 'Picked ' . $days . ' days ago');
                  }
                ?>
                <?php if ($freshLabel): ?>
                  <span class="badge text-bg-light"><?= e($freshLabel) ?></span>
                <?php endif; ?>
              </div>
              <div class="row align-items-start">
                <h2 class="listing-name h3 mb-0"><a href="listing.php?id=<?php echo (int) $item['id']; ?>" class="text-decoration-none"><?php echo e($item['name']); ?></a></h2>
              </div>
              <div class="d-flex justify-content-between align-items-baseline mt-1">
                <div class="price mb-0">
                  <?php if (!empty($item['discount_percent']) && (int) $item['discount_percent'] > 0 && !listing_is_expired($item)): ?>
                    <?php $dealPrice = round((float)$item['price_per_unit'] * (1 - (int)$item['discount_percent'] / 100), 2); ?>
                    <span class="text-decoration-line-through text-muted small me-1"><?= money($item['price_per_unit']) ?></span>
                    <span class="text-danger fw-bold"><?= money($dealPrice) ?></span>
                  <?php else: ?>
                    <?= money($item['price_per_unit']) ?>
                  <?php endif; ?>
                  <small class="text-muted">/ <?php echo e($item['unit_symbol'] ?? 'kg'); ?></small>
                </div>
              </div>
              <p class="text-muted small mb-0 mt-1"><i class="bi bi-geo-alt"></i> <a href="profile.php?id=<?= (int) $item['farmer_id'] ?>" class="text-decoration-none text-muted"><?= e($item['farm_name']) ?></a> · <?php echo e($item['province']); ?></p>
              <div class="card-actions">
                <?php if (listing_is_expired($item)): ?>
                  <span class="btn btn-sm btn-outline-secondary disabled w-100">Expired — not available</span>
                <?php elseif (!current_user_id()): ?>
                  <?php $guestReturn = 'listing.php?id=' . (int) $item['id']; ?>
                  <a class="buy-now btn btn-primary btn-sm w-100" href="<?php echo e(login_url($guestReturn)); ?>" aria-label="Buy now"><i class="bi bi-lightning-charge-fill me-1" aria-hidden="true"></i>Buy Now</a>
                  <a class="btn btn-outline-primary btn-icon btn-sm" href="<?php echo e(login_url($guestReturn)); ?>" title="Add to cart" aria-label="Add to cart"><i class="bi bi-cart-plus" aria-hidden="true"></i></a>
                  <a class="save-btn btn btn-outline-danger" href="<?php echo e(login_url($guestReturn)); ?>" title="Save for later" aria-label="Save for later"><i class="bi bi-heart" aria-hidden="true"></i></a>
                <?php elseif (in_array(current_user_role(), ['buyer', 'admin', 'manager'], true)): ?>
                  <?php if ($item['farmer_id'] !== current_user_id()): ?>
                  <form method="post" action="cart_add.php" class="mb-0 buy-now" data-buy-now="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="listing_id" value="<?php echo (int) $item['id']; ?>">
                    <input type="hidden" name="quantity" value="<?php echo (float) ($item['minimum_order_qty'] ?: $item['available_quantity'] ?: 1); ?>">
                    <input type="hidden" name="buy_now" value="1">
                    <button type="submit" class="btn btn-primary btn-sm w-100" aria-label="Buy now"><i class="bi bi-lightning-charge-fill me-1" aria-hidden="true"></i>Buy Now</button>
                  </form>
                  <form method="post" action="cart_add.php" class="mb-0" data-ajax="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="listing_id" value="<?php echo (int) $item['id']; ?>">
                    <input type="hidden" name="quantity" value="<?php echo (float) ($item['minimum_order_qty'] ?: $item['available_quantity'] ?: 1); ?>">
                    <button type="submit" class="btn btn-outline-primary btn-icon btn-sm" title="Add to cart" aria-label="Add to cart"><i class="bi bi-cart-plus" aria-hidden="true"></i></button>
                  </form>
                  <?php else: ?>
                    <span class="btn btn-sm btn-outline-secondary disabled">Your listing</span>
                  <?php endif; ?>
                  <form method="post" action="saved_toggle.php" class="mb-0" data-save-ajax="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="listing_id" value="<?php echo (int) $item['id']; ?>">
                    <input type="hidden" name="return_to" value="browse.php">
                    <button type="submit" class="save-btn btn btn-outline-danger" aria-label="<?= in_array((int) $item['id'], $savedIds) ? 'Remove from saved' : 'Save for later'; ?>" aria-pressed="<?= in_array((int) $item['id'], $savedIds) ? 'true' : 'false'; ?>">
                      <i class="bi <?= in_array((int) $item['id'], $savedIds) ? 'bi-heart-fill' : 'bi-heart'; ?>" aria-hidden="true"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <a class="btn-icon" href="listing.php?id=<?php echo (int) $item['id']; ?>" aria-label="View listing" title="View listing"><i class="bi bi-eye" aria-hidden="true"></i></a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <?php /* No listings to show. Two different situations land here and they
                 must not share one message: a marketplace with no stock, and a
                 marketplace full of stock that simply did not match $q. Only the
                 second one is a search result. The viewer state also decides
                 whether "log in" and "sign up" are advice or an insult. */ ?>
        <?php $searchMiss = trim($q) !== ''; $viewerId = current_user_id(); ?>
        <div style="grid-column: 1 / -1;">
          <?php
            // A load failure is not an empty marketplace, so it gets its own
            // message and a retry instead of the stock copy below.
            $icon = '<i class="bi bi-clipboard2" aria-hidden="true"></i>';

            if ($listingsFailed) {
                // The errors block is keyed locale-first, so the nested
                // `unavailable` group has to be resolved before it can be
                // indexed. Handing the nested group straight to $l() passes
                // null, and $l()'s `?? $key` fallback then renders the bare
                // words "title" / "message" as the page copy.
                $unavailable = $copy['errors'][$locale]['unavailable']
                    ?? $copy['errors']['en']['unavailable'] ?? [];
                $title = $unavailable['title'] ?? 'Marketplace unavailable';
                $description = $unavailable['message'] ?? '';
                $actions = [['label' => $unavailable['retry'] ?? 'Retry', 'href' => 'browse.php' . ($searchMiss ? '?q=' . urlencode($q) : ''), 'class' => 'btn-primary']];
            } elseif ($searchMiss) {
                $title = $locale === 'tl' ? 'Walang nahanap para sa "' . $q . '"' : 'No results for "' . $q . '"';
                $description = $locale === 'tl'
                    ? 'Walang aktibong listing na tumutugma sa paghahanap na ito. Subukang ibang salita o burahin ang teksto sa kahon sa itaas.'
                    : 'No active listings match this search. Try a different word, or clear the box above.';
                $actions = $viewerId
                    ? [
                        ['label' => $l($copy['nav'], 'sell'), 'href' => 'create_listing.php', 'class' => 'btn-primary'],
                        ['label' => $l($copy['nav'], 'dashboard'), 'href' => 'dashboard.php', 'class' => 'btn-outline'],
                      ]
                    : [
                        ['label' => $l($copy['nav'], 'login'), 'href' => login_url('browse.php'), 'class' => 'btn-primary'],
                        ['label' => $l($copy['auth'], 'create_one'), 'href' => 'register.php', 'class' => 'btn-outline-primary'],
                        ['label' => $l($copy['nav'], 'become_seller'), 'href' => 'register.php', 'class' => 'btn-outline-secondary'],
                      ];
            } elseif ($dealsFilter) {
                $title = $locale === 'tl' ? 'Walang deal ngayon' : 'No deals right now';
                $description = $locale === 'tl'
                    ? 'Wala pang listing na may diskwento. Subukang muli mamaya o mag-browse ng lahat ng produkto.'
                    : 'No discounted listings at the moment. Check back soon, or browse all products.';
                $actions = [['label' => 'Browse all', 'href' => 'browse.php', 'class' => 'btn-primary']];
            } elseif ($trendingFilter) {
                $title = $locale === 'tl' ? 'Walang trending ngayon' : 'No trending products yet';
                $description = $locale === 'tl'
                    ? 'Wala pang listing na maraming nag-save. Maging una — i-save ang mga gusto mo.'
                    : 'No listings have enough saves to be trending yet. Be the first to save the ones you like.';
                $actions = [['label' => 'Browse all', 'href' => 'browse.php', 'class' => 'btn-primary']];
            } else {
                $title = $locale === 'tl' ? 'Wala pang ibinebenta' : 'Nothing for sale yet';
                $description = $locale === 'tl'
                    ? 'Walang aktibong listing sa marketplace pa. Suriin muli pagkatapos ng ilang oras, o mag-login para pamahalaan ang iyong sariling listing.'
                    : 'No active listings in the marketplace right now. Check back in a few hours, or log in to manage your own listings.';
                $actions = $viewerId
                    ? [
                        ['label' => $l($copy['nav'], 'sell'), 'href' => 'create_listing.php', 'class' => 'btn-primary'],
                        ['label' => $l($copy['nav'], 'dashboard'), 'href' => 'dashboard.php', 'class' => 'btn-outline'],
                      ]
                    : [
                        ['label' => $l($copy['nav'], 'login'), 'href' => login_url('browse.php'), 'class' => 'btn-primary'],
                        ['label' => $l($copy['auth'], 'create_one'), 'href' => 'register.php', 'class' => 'btn-outline-primary'],
                        ['label' => $l($copy['nav'], 'become_seller'), 'href' => 'register.php', 'class' => 'btn-outline-secondary'],
                      ];
            }

            include __DIR__ . '/partials/empty-state.php';

        ?>
        </div>
      <?php endif; ?>
    </div>
    <!-- Map view -->
    <div class="mt-4" data-reveal>
      <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="toggle-map">
        <i class="bi bi-map me-1" aria-hidden="true"></i>Show farm map
      </button>
      <div id="browse-map" style="display:none;height:420px;border-radius:var(--radius-card);overflow:hidden;border:1px solid var(--borders);"></div>
    </div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    (function () {
      const pins = <?= json_encode($mapPins, JSON_UNESCAPED_UNICODE) ?>;
      let map = null;
      const btn = document.getElementById('toggle-map');
      const el = document.getElementById('browse-map');
      if (!btn || !el) return;
      btn.addEventListener('click', function () {
        if (el.style.display === 'none') {
          el.style.display = 'block';
          btn.innerHTML = '<i class="bi bi-list me-1" aria-hidden="true"></i>Show list';
          if (!map) {
            map = L.map('browse-map').setView([12.8797, 121.7740], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '&copy; OpenStreetMap contributors',
              maxZoom: 18,
            }).addTo(map);
            pins.forEach(function (p) {
              const lat = parseFloat(p.latitude);
              const lng = parseFloat(p.longitude);
              if (isNaN(lat) || isNaN(lng)) return;
              const m = L.marker([lat, lng]).addTo(map);
              m.bindPopup('<strong>' + p.name + '</strong><br>' + (p.farm_name || '') + '<br>PHP ' + p.price_per_unit + ' / ' + (p.unit_symbol || 'kg') + '<br><a href="listing.php?id=' + p.id + '">View listing</a>');
            });
          }
          setTimeout(function () { map.invalidateSize(); }, 100);
        } else {
          el.style.display = 'none';
          btn.innerHTML = '<i class="bi bi-map me-1" aria-hidden="true"></i>Show farm map';
        }
      });
    })();
    </script>
      <?php if (!$listingsFailed && $totalPages > 1): ?>
        <?php
        // Keep every active filter and the sort order across pages.
        $pagerParams = $_GET;
        unset($pagerParams['page']);
        $param = http_build_query($pagerParams);
        $labels = [
            'prev' => $l($copy['ui'], 'pager_prev'),
            'next' => $l($copy['ui'], 'pager_next'),
            'page' => $l($copy['ui'], 'pager_page'),
            'of'   => $l($copy['ui'], 'pager_of'),
        ];
        include __DIR__ . '/partials/pager.php';
        ?>
      <?php endif; ?>
  </div>
</section>
<?php if (!$sidebarShell): ?>
</main>
<?php endif; ?>

<div class="mobile-cta-bar" role="navigation" aria-label="Quick actions">
   <a href="browse.php" class="btn btn-outline">Browse</a>
   <a href="create_listing.php" class="btn btn-primary">Sell</a>
</div>

<?php /* The marketplace is a public page that a signed-in visitor happens to be
     viewing, so it keeps the public footer only for logged-out visitors; the
     logged-in shell uses dashboard-footer (no public footer). */ ?>
<?php /* React islands (proof of concept). Loaded only on browse.php; grows
       into the filter/grid island in a follow-up. Manifest keeps the hashed
       filename in sync after every `npm run build`. */ ?>
<?php
  $viteManifestPath = __DIR__ . '/assets/dist/.vite/manifest.json';
  $viteEntry = '';
  if (is_file($viteManifestPath)) {
      $viteManifest = json_decode((string) file_get_contents($viteManifestPath), true);
      if (is_array($viteManifest) && isset($viteManifest['assets/js/islands/main.jsx']['file'])) {
          $viteEntry = $viteManifest['assets/js/islands/main.jsx']['file'];
      }
  }
?>
<?php if ($viteEntry !== ''): ?>
<script type="module" src="assets/dist/<?= e($viteEntry) ?>"></script>
<?php endif; ?>
<script src="assets/js/browse-filters.js" defer></script>
<?php if ($sidebarShell): ?>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
<?php else: ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
<?php endif; ?>
