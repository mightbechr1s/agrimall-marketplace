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
$q = $_GET['q'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// Category label formatter (was inline, restored after cleanup)
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
    } catch (Exception $e) {}
}
require __DIR__ . '/partials/header.php';
?>

<!-- Marketplace -->
<main id="main-content" class="flex-grow-1">
<section class="section" id="market">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="kicker"><?php echo $l($copy['preview'], 'section_title'); ?></span>
      <h2>What's fresh today</h2>
      <p><?php echo $l($copy['preview'], 'intro'); ?></p>
    </div>

    <form class="search-form" method="get" action=".">
      <?php if (isset($_GET['lang'])): ?>
        <input type="hidden" name="lang" value="<?php echo htmlspecialchars($_GET['lang']); ?>">
      <?php endif; ?>
      <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($q); ?>">
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
      $useDb = true;
      try {
        $offset = ($page - 1) * 12;
        if ($q) {
          $stmt = DB::pdo()->prepare('SELECT * FROM v_active_listings WHERE status = "active" AND name LIKE :q ORDER BY published_at DESC LIMIT 12 OFFSET :offset');
          $stmt->bindValue(':q', '%'.$q.'%', PDO::PARAM_STR);
          $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
          $stmt->execute();
          $listings = $stmt->fetchAll();

          $totalStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM v_active_listings WHERE status = "active" AND name LIKE :q');
          $totalStmt->bindValue(':q', '%'.$q.'%', PDO::PARAM_STR);
          $total = $totalStmt->fetchColumn();
        } else {
          $stmt = DB::pdo()->prepare('SELECT * FROM v_active_listings WHERE status = "active" ORDER BY published_at DESC LIMIT 12 OFFSET :offset');
          $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
          $stmt->execute();
          $listings = $stmt->fetchAll();

          $total = DB::pdo()->query('SELECT COUNT(*) FROM v_active_listings WHERE status = "active"')->fetchColumn();
        }
        if (empty($listings)) {
          $useDb = false;
        }
      } catch (Exception $e) {
        $useDb = false;
      }

      if (!$useDb) {
        $listings = []; // trigger fallback
      }
      $totalPages = $useDb ? (int)ceil($total / 12) : 0; ?>
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
                'stitao' => 'assets/images/products/asparagus.jpg',
                'kangkong' => 'assets/images/products/market.jpg',
                'calamansi' => 'assets/images/products/oranges.jpg',
                'eggplant' => 'assets/images/products/seedlings.jpg',
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
              <img src="<?= e(asset($imagePath)) ?>" onerror="this.onerror=null;this.src='<?= e(asset($fallbackPath)) ?>'" alt="<?php echo e($item['name']); ?>" loading="lazy">
            </div>
            <div class="body">
              <div class="badges-top">
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
                  <a class="add-to-basket btn-icon" href="browse.php" aria-label="Add to cart"><i class="bi bi-cart-plus" aria-hidden="true"></i></a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <?php /* No listings in the database. Show an honest empty state and the
                 three things a visitor can actually do here. This branch used to
                 render mockup cards with invented prices and "Local Farm" as the
                 seller, which read as real inventory. */ ?>
        <div class="empty-state text-center" style="padding: 3rem 1.5rem;">
          <div class="empty-state-icon-wrapper mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon"><path d="M8 1a.5.5 0 0 1 .5.5V3h1.5a.5.5 0 0 1 0 1H8.5v1.5h1a.5.5 0 0 1 0 1h-1v1.5h1.5a.5.5 0 0 1 0 1H8.5V9.5h1a.5.5 0 0 1 0 1h-1V11h1.5a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V12H6a.5.5 0 0 1 0-1h1.5V9.5h-1a.5.5 0 0 1 0-1h1V7H7a.5.5 0 0 1 0-1h1.5V4.5H7a.5.5 0 0 1 0-1h.5V1.5A.5.5 0 0 1 8 1z"/><path d="M2 2.5a.5.5 0 0 1 .5.5V4h1a.5.5 0 0 1 0 1h-1v1h1a.5.5 0 0 1 0 1h-1v1h1a.5.5 0 0 1 0 1h-1v3.5a.5.5 0 0 1-1 0V8H1.5a.5.5 0 0 1 0-1H2V6H1a.5.5 0 0 1 0-1h1V4H1.5a.5.5 0 0 1 0-1H2V3a.5.5 0 0 1 .5-.5z"/></svg>
          </div>
          <h3 class="empty-state-title"><?= e($locale === 'tl' ? 'Wala pang ibinebenta' : 'Nothing for sale yet') ?></h3>
          <p class="empty-state-description"><?= e($locale === 'tl'
              ? 'Walang aktibong listing sa marketplace pa. Suriin muli pagkatapos ng ilang oras, o mag-login para pamahalaan ang iyong sariling listing.'
              : 'No active listings in the marketplace right now. Check back in a few hours, or log in to manage your own listings.') ?></p>
          <div class="empty-state-action mt-3 d-flex flex-wrap gap-2 justify-content-center">
            <a href="<?= e(login_url('browse.php')) ?>" class="btn btn-primary"><?= e($l($copy['nav'], 'login')) ?></a>
            <a href="register.php" class="btn btn-outline-primary"><?= e($l($copy['auth'], 'create_one')) ?></a>
            <a href="register.php" class="btn btn-outline-secondary"><?= e($l($copy['nav'], 'become_seller')) ?></a>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($useDb && $page < $totalPages): ?>
        <div style="text-align:center;margin-top:32px;">
          <a class="btn btn-outline load-more-btn" href="?page=<?php echo $page+1; ?><?php if (isset($_GET['lang'])): ?>&lang=<?php echo urlencode($_GET['lang']); ?><?php endif; ?><?php if ($q): ?>&q=<?php echo urlencode($q); ?><?php endif; ?>">Load more</a>
        </div>
      <?php endif; ?>
  </div>
</section>
</main>

<script>
(function () {
  'use strict';
  document.addEventListener('click', async function (e) {
    var btn = e.target.closest('.load-more-btn');
    if (!btn) return;
    e.preventDefault();
    var url = btn.href;
    try {
      var resp = await fetch(url, {
        headers: { 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      });
      if (!resp.ok) return;
      var html = await resp.text();
      var parser = new DOMParser();
      var doc = parser.parseFromString(html, 'text/html');
      var newCards = doc.querySelectorAll('#product-grid .card');
      var grid = document.getElementById('product-grid');
      newCards.forEach(function (card) {
        grid.appendChild(card.cloneNode(true));
      });
      var nextBtn = doc.querySelector('.load-more-btn');
      if (nextBtn) {
        btn.href = nextBtn.href;
      } else {
        btn.remove();
      }
    } catch (err) {
      // fallback to normal navigation
      window.location.href = url;
    }
  });
})();
</script>

<div class="mobile-cta-bar" role="navigation" aria-label="Quick actions">
   <a href="browse.php" class="btn btn-outline">Browse</a>
   <a href="create_listing.php" class="btn btn-primary">Sell</a>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>