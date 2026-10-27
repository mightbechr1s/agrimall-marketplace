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
$page = max(1, (int)($_GET['page'] ?? 1));

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
if (current_user_id() && current_user_role() === 'buyer') {
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
    $activePage = $role === 'buyer' ? 'browse' : ($role === 'admin' ? 'goods' : 'market');
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

    <form class="search-form" method="get" action="browse.php">
      <?php if (isset($_GET['lang'])): ?>
        <input type="hidden" name="lang" value="<?php echo htmlspecialchars($_GET['lang']); ?>">
      <?php endif; ?>
      <label class="visually-hidden" for="market-search"><?= e($l($copy['search'], 'label')) ?></label>
      <input id="market-search" type="search" name="q" placeholder="<?= e($l($copy['search'], 'placeholder')) ?>" value="<?php echo htmlspecialchars($q); ?>">
    </form>
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
        // Static fallback categories matching the fallback cards
        $categories = ['fruits', 'livestock-dairy', 'poultry', 'seafood', 'vegetables'];
        sort($categories);
      }
      ?>
      <button class="filter-pill is-active" type="button" data-filter="all">All</button>
      <?php foreach ($categories as $cat): ?>
        <button class="filter-pill" type="button" data-filter="<?php echo strtolower($cat); ?>"><?php echo htmlspecialchars(formatCatLabel($cat)); ?></button>
      <?php endforeach; ?>
    </div>

    <div class="grid" id="product-grid" data-reveal="stagger">
      <?php
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
          $idsStmt = DB::pdo()->prepare(
            "SELECT id FROM produce_listings
              WHERE status = 'active'
                AND (MATCH(name, description) AGAINST(:fts IN NATURAL LANGUAGE MODE) OR name LIKE :like)");
          $idsStmt->execute([':fts' => $q, ':like' => '%' . $q . '%']);
          $matchIds = array_map('intval', $idsStmt->fetchAll(PDO::FETCH_COLUMN));
          $where = $matchIds
            ? 'id IN (' . implode(',', $matchIds) . ')'
            : '1 = 0';   // no hits: count and grid both come up empty
        } else {
          $where = "status = 'active'";
        }

        // Count first: an out-of-range ?page= (bookmark, edited URL) is pulled back
        // onto the last page before it can become an OFFSET and show an empty grid.
        $countStmt = DB::pdo()->prepare("SELECT COUNT(*) FROM v_active_listings WHERE $where");
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / 12);
        if ($totalPages > 0 && $page > $totalPages) {
          $page = $totalPages;
        }

        $offset = ($page - 1) * 12;
        $stmt = DB::pdo()->prepare("SELECT * FROM v_active_listings WHERE $where ORDER BY published_at DESC LIMIT 12 OFFSET :offset");
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $listings = $stmt->fetchAll();
      } catch (Exception $e) {
        $listingsFailed = true;
        error_log('browse: listing query failed: ' . $e->getMessage());
      }

      $totalPages = $listingsFailed ? 0 : $totalPages; ?>
      <?php if ($listings): ?>
        <?php foreach ($listings as $item): ?>
          <article class="card" data-reveal="stagger" data-category="<?php echo e(strtolower($item['category_name'] ?? '')); ?>">
            <div class="swipe-reveal swipe-reveal--left" aria-hidden="true"><i class="bi bi-star"></i></div>
            <div class="swipe-reveal swipe-reveal--right" aria-hidden="true"><i class="bi bi-basket3"></i></div>
            <?php
              $categorySlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $item['category_name'] ?? ''), '-'));
              $nameSlug = strtolower($item['name'] ?? '');
              $fallbackImages = [
                'fruits' => 'assets/images/products/mango.jpg',
                'vegetables' => 'assets/images/products/market.jpg',
                'leafy-greens' => 'assets/images/products/market.jpg',
                'root-crops' => 'assets/images/listings/rice.svg',
                'grains' => 'assets/images/listings/rice.svg',
                'grains-legumes' => 'assets/images/listings/rice.svg',
                'livestock-dairy' => 'assets/images/listings/milk.svg',
                'dairy' => 'assets/images/listings/milk.svg',
                'poultry' => 'assets/images/listings/chicken.svg',
                'meat-poultry' => 'assets/images/listings/chicken.svg',
                'seafood' => 'assets/images/products/seafood.jpg',
                'fish' => 'assets/images/products/seafood.jpg',
                'fish-seafood' => 'assets/images/products/seafood.jpg',
              ];
              $nameImages = [
                // Most specific name first: the loop below breaks on first match,
                // and there are both string-bean and long-bean sitaw listings.
                'string beans' => 'assets/images/products/Sitaw_Long_Beans.jpg',
                'sitaw' => 'assets/images/products/Sitaw_Long_Beans.jpg',
                'kangkong' => 'assets/images/products/market.jpg',
                'calamansi' => 'assets/images/products/Calamansi.jpg',
                'eggplant' => 'assets/images/products/Eggplant.jpg',
                'sweet potato' => 'assets/images/listings/rice.svg',
                'rice' => 'assets/images/listings/rice.svg',
              ];
              $fallbackPath = $fallbackImages[$categorySlug] ?? 'assets/images/listings/mangoes.svg';
              foreach ($nameImages as $name => $candidate) {
                if (str_contains($nameSlug, $name)) {
                  $fallbackPath = $candidate;
                  break;
                }
              }
              $imagePath = $item['primary_image'] ?: $fallbackPath;
            ?>
            <div class="thumb" style="background: var(--canvas);">
              <img src="<?= e(asset($imagePath)) ?>" onerror="this.onerror=null;this.src='<?= e(asset($fallbackPath)) ?>'" alt="<?php echo e($item['name']); ?>" loading="lazy" width="400" height="400">
            </div>
            <div class="body">
              <div class="badges-top">
                <?php if (listing_is_expired($item)): ?>
                  <?php // FR-LIST-033: expired produce stays visible in the catalogue, but must be presented as expired. ?>
                  <span class="badge text-bg-warning" data-testid="expired-badge"><?= $l($copy['listing'], 'expired_badge') ?></span>
                <?php endif; ?>
                <span class="badge badge-fresh">Picked Today</span>
                <span class="badge badge-location"><i class="bi bi-geo-alt"></i><?php echo e($item['farm_name']); ?> · <?php echo e($item['province']); ?></span>
              </div>
              <div class="row">
                <h3><a href="listing.php?id=<?php echo (int) $item['id']; ?>" class="text-decoration-none listing-name"><?php echo e($item['name']); ?></a></h3>
              </div>
              <div class="price"><?php echo money($item['price_per_unit']); ?> <small>/ <?php echo e($item['unit_symbol'] ?? 'kg'); ?></small></div>
              <div class="card-actions">
                <?php if (!current_user_id()): ?>
                  <?php $guestReturn = 'listing.php?id=' . (int) $item['id']; ?>
                  <a class="buy-now btn btn-primary btn-sm w-100" href="<?php echo e(login_url($guestReturn)); ?>" aria-label="Buy now"><i class="bi bi-lightning-charge-fill me-1" aria-hidden="true"></i>Buy Now</a>
                  <a class="btn btn-outline-primary btn-icon btn-sm" href="<?php echo e(login_url($guestReturn)); ?>" title="Add to cart" aria-label="Add to cart"><i class="bi bi-cart-plus" aria-hidden="true"></i></a>
                  <a class="save-btn btn btn-outline-danger" href="<?php echo e(login_url($guestReturn)); ?>" title="Save for later" aria-label="Save for later"><i class="bi bi-heart" aria-hidden="true"></i></a>
                <?php elseif (current_user_role() === 'buyer'): ?>
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
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon"><path d="M8 1a.5.5 0 0 1 .5.5V3h1.5a.5.5 0 0 1 0 1H8.5v1.5h1a.5.5 0 0 1 0 1h-1v1.5h1.5a.5.5 0 0 1 0 1H8.5V9.5h1a.5.5 0 0 1 0 1h-1V11h1.5a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V12H6a.5.5 0 0 1 0-1h1.5V9.5h-1a.5.5 0 0 1 0-1h1V7H7a.5.5 0 0 1 0-1h1.5V4.5H7a.5.5 0 0 1 0-1h.5V1.5A.5.5 0 0 1 8 1z"/><path d="M2 2.5a.5.5 0 0 1 .5.5V4h1a.5.5 0 0 1 0 1h-1v1h1a.5.5 0 0 1 0 1h-1v1h1a.5.5 0 0 1 0 1h-1v3.5a.5.5 0 0 1-1 0V8H1.5a.5.5 0 0 1 0-1H2V6H1a.5.5 0 0 1 0-1h1V4H1.5a.5.5 0 0 1 0-1H2V3a.5.5 0 0 1 .5-.5z"/>';

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
      <?php if (!$listingsFailed && $totalPages > 1): ?>
        <?php
        $paramParts = [];
        if (isset($_GET['lang'])) $paramParts[] = 'lang=' . urlencode($_GET['lang']);
        if ($q !== '') $paramParts[] = 'q=' . urlencode($q);
        $param = implode('&', $paramParts);
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
     viewing, so it keeps the public footer in both shells -- and the footer
     already hides the seller link for signed-in visitors. */ ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
<?php if ($sidebarShell): ?>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
<?php endif; ?>