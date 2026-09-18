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

    <!-- Featured Bulk Deal hero card -->
    <div class="feature-card" data-reveal="stagger">
      <div class="thumb">
        <img src="assets/images/products/mango.jpg" onerror="this.onerror=null;this.src='assets/images/listings/mangoes.svg'" alt="Featured Bulk Deal" loading="lazy">
      </div>
      <div class="body">
        <div class="row">
          <h3>Bulk deal</h3>
          <span class="badge badge-fresh">Picked Today</span>
        </div>
        <p>Bulk produce for home or business.</p>
        <div class="price">PHP 999 <small>/ 10kg</small></div>
        <button class="add-to-basket" type="button">View deal</button>
      </div>
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
                <h3><?php echo e($item['name']); ?></h3>
              </div>
              <div class="price"><?php echo money($item['price_per_unit']); ?> <small>/ <?php echo e($item['unit_symbol'] ?? 'kg'); ?></small></div>
              <button class="add-to-basket" type="button" aria-label="Add <?php echo e($item['name']); ?> to basket"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add to basket</button>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <!-- Fallback cards from mockup -->
        <?php
        $fallback = [
          ['name'=>'Carabao Mangoes — 1 kg', 'farm'=>'Encarnacion Farm · Guimaras', 'price'=>180, 'unit'=>'kg', 'cat'=>'fruits', 'badge'=>'Today', 'badgeCls'=>'badge-fresh', 'image'=>'assets/images/listings/mangoes.svg'],
          ['name'=>'Fresh Carabao Milk — 1 L', 'farm'=>'Delagracia Farm · Laguna', 'price'=>120, 'unit'=>'L', 'cat'=>'livestock-dairy', 'badge'=>'Today', 'badgeCls'=>'badge-fresh', 'image'=>'assets/images/listings/milk.svg'],
          ['name'=>'Free-Range Native Chicken', 'farm'=>'Mercado Farm · Batangas', 'price'=>390, 'unit'=>'head', 'cat'=>'poultry', 'badge'=>'Today', 'badgeCls'=>'badge-fresh', 'image'=>'assets/images/listings/chicken.svg'],
          ['name'=>'Fresh Bangus — 1 kg', 'farm'=>'Dizon Aquaculture · Dagupan', 'price'=>240, 'unit'=>'kg', 'cat'=>'seafood', 'badge'=>'2 days', 'badgeCls'=>'badge-normal', 'image'=>'assets/images/listings/bangus.svg'],
          ['name'=>'Organic Rice — 5 kg', 'farm'=>'Encarnacion Farm · Guimaras', 'price'=>350, 'unit'=>'sack', 'cat'=>'vegetables', 'badge'=>'1 day', 'badgeCls'=>'badge-normal', 'image'=>'assets/images/listings/rice.svg'],
          ['name'=>'Balut & Duck Eggs — 15 pcs', 'farm'=>'Pateros Co-op · Rizal', 'price'=>150, 'unit'=>'tray', 'cat'=>'poultry', 'badge'=>'4 days', 'badgeCls'=>'badge-stale', 'image'=>'assets/images/listings/eggs.svg'],
        ];
        foreach ($fallback as $card): ?>
          <article class="card" data-reveal="stagger" data-category="<?php echo e($card['cat']); ?>">
            <div class="swipe-reveal swipe-reveal--left" aria-hidden="true"><i class="bi bi-star"></i></div>
            <div class="swipe-reveal swipe-reveal--right" aria-hidden="true"><i class="bi bi-basket3"></i></div>
            <div class="thumb" style="background: var(--canvas);">
              <img src="<?= e(asset($card['image'])) ?>" alt="<?php echo e($card['name']); ?>" loading="lazy">
            </div>
            <div class="body">
              <div class="badges-top">
                <span class="badge badge-fresh">Picked Today</span>
                <span class="badge badge-location"><i class="bi bi-geo-alt"></i><?php echo e($card['farm']); ?></span>
              </div>
              <div class="row">
                <h3><?php echo e($card['name']); ?></h3>
                <span class="badge <?php echo e($card['badgeCls']); ?>"><?php echo e($card['badge']); ?></span>
              </div>
              <div class="price">PHP <?php echo number_format($card['price'], 0); ?> <small>/ <?php echo e($card['unit']); ?></small></div>
              <button class="add-to-basket" type="button" aria-label="Add <?php echo e($card['name']); ?> to basket"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add to basket</button>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if ($useDb && $page < $totalPages): ?>
        <div style="text-align:center;margin-top:32px;">
          <a class="btn btn-outline" href="?page=<?php echo $page+1; ?><?php if (isset($_GET['lang'])): ?>&lang=<?php echo urlencode($_GET['lang']); ?><?php endif; ?><?php if ($q): ?>&q=<?php echo urlencode($q); ?><?php endif; ?>">Load more</a>
        </div>
      <?php endif; ?>
  </div>
</section>
</main>

<div class="mobile-cta-bar" role="navigation" aria-label="Quick actions">
   <a href="browse.php" class="btn btn-outline">Browse</a>
   <a href="create_listing.php" class="btn btn-primary">Sell</a>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>