<?php
/**
 * Listing Card Partial — renders a product card for grid/horizontal/featured modes.
 * Required: $listing (array with id, title, price, unit, category, farmer_name, farmer_slug, image_path, is_fresh, is_saved, qty_available).
 * Optional: $mode ('grid'|'horizontal'|'farmer'|'featured'), $showActions (bool, default true for buyers).
 */
$mode = $mode ?? 'grid';
$showActions = $showActions ?? true;
$isSaved = $listing['is_saved'] ?? false;
$isFresh = $listing['is_fresh'] ?? false;
$unit = $listing['unit'] ?? 'kg';
$imageUrl = asset($listing['image_path'] ?: fallback_produce_image());
$listingUrl = "listing.php?id=" . (int) $listing['id'];
$farmerUrl = ($listing['farmer_slug'] ?? '') ? "profile.php?u=" . urlencode($listing['farmer_slug']) : 'browse.php';
// Relative path (no leading slash): is_safe_path() rejects absolute; raw REQUEST_URI would fall back to dashboard.
$returnPath = ltrim((string) ($_SERVER['REQUEST_URI'] ?? ''), '/') ?: 'browse.php';

$freshClass = $isFresh ? 'badge-fresh' : ($listing['is_stale'] ?? false ? 'badge-stale' : 'badge-normal');
$freshLabel = $isFresh ? 'Fresh' : ($listing['is_stale'] ?? false ? 'Stale' : 'Available');
// Farmer mode manages its own stock, so the card leads with the listing status
// rather than buyer-facing freshness.
$isFarmer = $mode === 'farmer';
$status = $listing['status'] ?? 'active';
// Farmer cards mirror the marketplace card (browse.php) so the grid reads the same;
// grid/horizontal/featured keep the taller h-100 card.
$cardClasses = $isFarmer ? 'card' : 'card h-100';
if ($mode === 'horizontal') $cardClasses .= ' flex-row';
if ($mode === 'featured') $cardClasses .= ' feature-card';
?>
<article class="<?= e($cardClasses) ?>" data-reveal="stagger" data-category="<?= e(strtolower($listing['category'] ?? '')) ?>" itemscope itemtype="https://schema.org/Product">
  <div class="thumb" itemprop="image">
    <?php if (!$isFarmer): ?>
    <div class="badges-top">
      <span class="badge <?= e($freshClass) ?>" itemprop="availability" content="http://schema.org/InStock"><?= e($freshLabel) ?></span>
      <span class="badge badge-normal"><?= e($listing['category'] ?? 'Produce') ?></span>
    </div>
    <?php endif; ?>
    <?php if ($mode === 'featured'): ?>
      <img src="<?= e($imageUrl) ?>" alt="" loading="eager" width="600" height="450" class="w-100 h-100 object-fit-cover" onerror="this.onerror=null;this.src='assets/images/products/market.jpg'">
    <?php else: ?>
      <img src="<?= e($imageUrl) ?>" alt="<?= e($listing['title'] ?? 'Produce') ?>" loading="lazy" width="400" height="400" class="w-100 h-100 object-fit-cover" onerror="this.onerror=null;this.src='assets/images/products/market.jpg'">
    <?php endif; ?>
    <?php if ($mode === 'featured' && !empty($listing['origin'])): ?>
      <span class="origin-badge"><?= e($listing['origin']) ?></span>
    <?php endif; ?>
  </div>

  <div class="body d-flex flex-column">
    <?php if ($isFarmer): ?>
      <div class="badges-top">
        <?php if (listing_is_expired($listing)): ?>
          <span class="badge text-bg-warning" data-testid="expired-badge"><?= $l($copy['listing'], 'expired_badge') ?></span>
        <?php endif; ?>
        <span class="badge <?= $status === 'active' ? 'badge-fresh' : 'badge-normal' ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span>
        <span class="badge badge-location"><i class="bi bi-geo-alt" aria-hidden="true"></i><?= e($listing['farm_name'] ?? '') ?><?= !empty($listing['province']) ? ' · ' . e($listing['province']) : '' ?></span>
      </div>
    <?php endif; ?>

    <div class="row">
      <div class="col">
        <h3 class="listing-name" itemprop="name"><a href="<?= e($listingUrl) ?>"><?= e($listing['title'] ?? 'Untitled Listing') ?></a></h3>
      </div>
    </div>

    <?php if (!$isFarmer): ?>
    <div class="row">
      <div class="col text-soft small">
        <a href="<?= e($farmerUrl) ?>" class="farmer-meta text-decoration-none">
          <i class="bi bi-person-fill" aria-hidden="true"></i><span class="farmer-name"><?= e($listing['farmer_name'] ?? 'Unknown Farmer') ?></span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <div class="row" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
      <div class="col">
        <span class="price" itemprop="price" content="<?= e(number_format($listing['price'], 2)) ?>">
          ₱<?= e(number_format($listing['price'], 2)) ?>
          <small>/<?= e($unit) ?></small>
        </span>
        <meta itemprop="priceCurrency" content="PHP">
        <link itemprop="availability" href="http://schema.org/InStock">
      </div>
    </div>

    <?php if ($isFarmer): ?>
      <div class="card-actions mt-auto">
        <div class="d-flex gap-2 align-items-center">
          <a href="<?= e($listingUrl) ?>" class="btn btn-outline-primary btn-sm flex-grow-1 px-1">
            <i class="bi bi-eye me-1" aria-hidden="true"></i>View
          </a>
          <a href="create_listing.php?edit=<?= (int) $listing['id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1 px-1">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit
          </a>
          <form method="post" action="listing_action.php" class="mb-0 flex-grow-1"
                onsubmit="return confirm('Delete this listing? Buyers will no longer see it.');">
            <?= csrf_field() ?>
            <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
            <input type="hidden" name="return_to" value="my_listings.php">
            <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm w-100 px-1">
              <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete
            </button>
          </form>
        </div>
      </div>
    <?php elseif ($showActions): ?>
      <div class="card-actions mt-auto">
        <div class="d-flex gap-2 align-items-center">
          <form method="post" action="cart_add.php" class="mb-0" data-buy-now="true">
            <?= csrf_field() ?>
            <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
            <input type="hidden" name="quantity" value="<?= (float) ($listing['minimum_order_qty'] ?? 1) ?>">
            <input type="hidden" name="buy_now" value="1">
            <input type="hidden" name="return_to" value="<?= e($returnPath) ?>">
            <button type="submit" class="btn btn-primary btn-sm w-100" aria-label="Buy now">
              <i class="bi bi-lightning-charge-fill me-1" aria-hidden="true"></i>Buy Now
            </button>
          </form>
          <form method="post" action="cart_add.php" class="mb-0" data-ajax="true">
            <?= csrf_field() ?>
            <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
            <input type="hidden" name="quantity" value="<?= (float) ($listing['minimum_order_qty'] ?? 1) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnPath) ?>">
            <button type="submit" class="btn btn-outline-primary btn-icon" title="Add to cart" aria-label="Add to cart">
              <i class="bi bi-cart-plus" aria-hidden="true"></i>
            </button>
          </form>
          <form method="post" action="saved_toggle.php" class="mb-0" data-save-ajax="true">
            <?= csrf_field() ?>
            <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
            <input type="hidden" name="return_to" value="<?= e($returnPath) ?>">
            <button type="submit" class="save-btn btn btn-outline-secondary btn-icon" aria-pressed="<?= $isSaved ? 'true' : 'false' ?>" aria-label="<?= $isSaved ? 'Remove from saved' : 'Save listing' ?>">
              <i class="bi <?= $isSaved ? 'bi-bookmark-fill' : 'bi-bookmark' ?>" aria-hidden="true"></i>
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</article>