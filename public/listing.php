<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$dbError = false;
try {
    $stmt = DB::pdo()->prepare("SELECT pl.*, pc.name AS category_name, pc.slug AS category_slug, qu.symbol AS unit_symbol, qu.type AS unit_type, qu.step AS unit_step, fp.farm_name, fp.barangay, fp.municipality_city, fp.province, fp.verification_status, (SELECT li.image_path FROM listing_images li WHERE li.listing_id = pl.id AND li.is_primary = 1 ORDER BY li.sort_order LIMIT 1) AS primary_image FROM produce_listings pl JOIN produce_categories pc ON pl.category_id = pc.id JOIN quantity_units qu ON pl.unit_id = qu.id JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id WHERE pl.id = :id AND pl.status = 'active' AND fp.verification_status = 'approved' AND pc.is_active = 1 AND qu.is_active = 1 AND pl.available_quantity > 0");
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();
} catch (Throwable $e) {
    $item = null;
    $dbError = true;
}

$pageTitle = ($item['name'] ?? null) ?: 'Marketplace';
$activeNav = 'browse';
$locale = locale_current();
$l = function($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};
$role = function_exists('current_user_role') ? current_user_role() : null;
$sidebarShell = (bool) current_user_id();
if ($sidebarShell) {
    $activePage = $role === 'buyer' ? 'browse' : 'market';
    require __DIR__ . '/partials/dashboard-layout.php';
} else {
    require __DIR__ . '/partials/header.php';
}

if (!$item) {
    if (!headers_sent()) {
        http_response_code($dbError ? 503 : 404);
    }
    ?>
    <?php if (!$sidebarShell): ?>
    <main id="main-content" class="container py-5" style="max-width: 640px;">
    <?php endif; ?>
        <div class="dash-empty mt-5">
            <i class="bi bi-basket3"></i>
            <p><?= $l($copy['listing'], $dbError ? 'temp_unavailable' : 'listing_gone') ?></p>
            <a class="btn btn-primary" href="browse.php"><?= $l($copy['listing'], 'back_to_market') ?></a>
        </div>
    <?php if (!$sidebarShell): ?>
    </main>
    <?php endif; ?>
    <?php require $sidebarShell ? __DIR__ . '/partials/dashboard-footer.php' : __DIR__ . '/partials/footer.php';
    exit;
    }

// Get listing images
$imageStmt = DB::pdo()->prepare('SELECT image_path FROM listing_images WHERE listing_id = :id ORDER BY is_primary DESC, sort_order');
$imageStmt->execute(['id' => $id]);
$images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = $item['name'];
$fmtQty = function ($value, $symbol): string {
    return rtrim(rtrim(number_format((float) $value, 4), '0'), '.') . ' ' . e($symbol);
};
?>

<?php if (!$sidebarShell): ?>
<main id="main-content" class="container py-5">
<?php endif; ?>
    <div class="mb-3">
        <button type="button" class="btn btn-outline btn-sm" onclick="if (history.length > 1) history.back(); else location.href = 'browse.php';" aria-label="Back">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= $l($copy['listing'], 'back_to_market') ?? 'Back to marketplace' ?>
        </button>
    </div>
    <div class="listing-wrap row g-5">
        <div class="col-lg-6" data-reveal>
            <?php if (!empty($images)): ?>
                <div id="listingImageCarousel" class="carousel slide listing-gallery" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php foreach ($images as $index => $imageUrl): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                <img src="<?= e($imageUrl) ?>" class="d-block w-100" alt="<?= e($item['name']) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="carousel-indicators">
                            <?php foreach ($images as $index => $imageUrl): ?>
                                <button type="button" data-bs-target="#listingImageCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index === 0 ? 'active' : '' ?>" aria-label="Image <?= $index + 1 ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <button class="carousel-control-prev" type="button" aria-label="Previous" data-bs-target="#listingImageCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" aria-label="Next" data-bs-target="#listingImageCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="listing-placeholder" data-reveal>
                    <div class="placeholder-icon d-flex align-items-center justify-content-center">
                        <i class="bi bi-basket3" aria-hidden="true"></i>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-6" data-reveal>
            <div class="listing-info">
                <div class="listing-pills">
                    <?php if ((int) $item['is_organic'] === 1): ?>
                        <span class="pill pill--organic"><i class="bi bi-leaf" aria-hidden="true"></i><?= $l($copy['listing'], 'organic') ?></span>
                    <?php endif; ?>
                    <span class="pill pill--category"><?= e($item['category_name']) ?></span>
                    <?php if ($item['verification_status'] === 'approved'): ?>
                        <span class="pill pill--verified"><i class="bi bi-patch-check-fill" aria-hidden="true"></i><?= $l($copy['listing'], 'verified_farm') ?></span>
                    <?php endif; ?>
                </div>

                <h1 class="listing-title"><?= e($item['name']) ?></h1>
                <p class="listing-origin">
                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                    <?= e($item['farm_name']) ?> &middot; <?= e($item['barangay']) ?>, <?= e($item['municipality_city']) ?>, <?= e($item['province']) ?>
                </p>

                <div class="listing-price">
                    <span class="amount"><?= money($item['price_per_unit']) ?></span>
                    <span class="per">/ <?= e($item['unit_symbol']) ?></span>
                </div>
                <div class="listing-meta">
                    <?= $fmtQty($item['available_quantity'], $item['unit_symbol']) ?> <?= $l($copy['listing'], 'available') ?>
                    <span class="sep">&middot;</span>
                    <?= $l($copy['listing'], 'min_order') ?> <?= $fmtQty($item['minimum_order_qty'], $item['unit_symbol']) ?>
                    <span class="sep">&middot;</span>
                    <span class="text-muted small">Min. total: <?= money($item['price_per_unit'] * $item['minimum_order_qty']) ?> for <?= $fmtQty($item['minimum_order_qty'], $item['unit_symbol']) ?></span>
                </div>

                <p class="listing-desc"><?= nl2br(e($item['description'] ?? '')) ?></p>

                <dl class="listing-facts">
                    <?php if ($item['harvest_date']): ?>
                        <div><dt><?= $l($copy['listing'], 'harvest_date') ?></dt><dd><?= e($item['harvest_date']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($item['expiry_date']): ?>
                        <div><dt><?= $l($copy['listing'], 'best_before') ?></dt><dd><?= e($item['expiry_date']) ?><?php if (listing_is_expired($item)): ?> <span class="badge text-bg-warning" data-testid="expired-badge"><?= $l($copy['listing'], 'expired_badge') ?></span><?php endif; ?></dd></div>
                    <?php endif; ?>
                    <?php if ($item['maximum_order_qty']): ?>
                        <div><dt><?= $l($copy['listing'], 'max_order') ?></dt><dd><?= $fmtQty($item['maximum_order_qty'], $item['unit_symbol']) ?></dd></div>
                    <?php endif; ?>
                </dl>

                <div class="listing-cta" data-reveal>
                    <?php if (listing_is_expired($item)): ?>
                        <div class="alert alert-warning" role="alert" data-testid="expired-notice">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= $l($copy['listing'], 'expired_notice') ?>
                        </div>
                        <p class="text-muted mb-0"><?= $l($copy['listing'], 'expired_no_order') ?></p>
                    <?php elseif (current_user_id() && current_user_role() === 'buyer'): ?>
                        <?php
                        $isSaved = false;
                        try {
                            $sv = DB::pdo()->prepare('SELECT 1 FROM saved_listings WHERE user_id = :u AND listing_id = :l');
                            $sv->execute(['u' => current_user_id(), 'l' => $item['id']]);
                            $isSaved = (bool) $sv->fetchColumn();
                        } catch (Exception $e) {
                            // Decorative query: degrade rather than break the page, but do not
                            // swallow it silently -- a failure here is invisible otherwise.
                            error_log('listing: saved-state lookup failed: ' . $e->getMessage());
                        }
                        ?>
                        <div class="d-flex gap-2 mb-3">
                            <form method="post" action="cart_add.php" class="mb-0" data-buy-now="true">
                                <?= csrf_field() ?>
                                <input type="hidden" name="listing_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="quantity" value="<?= (float) $item['minimum_order_qty'] ?>">
                                <input type="hidden" name="buy_now" value="1">
                                <button type="submit" class="btn btn-primary" aria-label="Buy now"><i class="bi bi-lightning-charge-fill me-1" aria-hidden="true"></i>Buy Now</button>
                            </form>
                            <form method="post" action="cart_add.php" class="mb-0" data-ajax="true">
                                <?= csrf_field() ?>
                                <input type="hidden" name="listing_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="quantity" value="<?= (float) $item['minimum_order_qty'] ?>">
                                <button type="submit" class="btn btn-outline-primary btn-icon" title="Add to cart" aria-label="Add to cart"><i class="bi bi-cart-plus" aria-hidden="true"></i></button>
                            </form>
                            <form method="post" action="saved_toggle.php" class="mb-0" data-save-ajax="true">
                                <?= csrf_field() ?>
                                <input type="hidden" name="listing_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="return_to" value="listing.php?id=<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn <?= $isSaved ? 'btn-danger' : 'btn-outline-danger' ?>">
                                    <i class="bi <?= $isSaved ? 'bi-heart-fill' : 'bi-heart' ?> me-1" aria-hidden="true"></i><?= $isSaved ? 'Saved' : 'Save for Later' ?>
                                </button>
                            </form>
                        </div>
                        <div class="order-form">
                            <h5 class="mb-3">Place Order</h5>
                            <form method="post" action="/order_request.php" class="row g-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="listing_id" value="<?= (int) $item['id'] ?>">

                                <div class="col-md-6">
                                    <label class="form-label" for="quantity">Quantity (<?= e($item['unit_symbol']) ?>)</label>
                                    <input type="number" step="0.01" min="<?= (float) $item['minimum_order_qty'] ?>"
                                           max="<?= $item['maximum_order_qty'] ? (float) $item['maximum_order_qty'] : (float) $item['available_quantity'] ?>"
                                           class="form-control" id="quantity" name="quantity"
                                           value="<?= (float) $item['minimum_order_qty'] ?>" required>
                                    <div class="form-text">
                                        Available: <?= $fmtQty($item['available_quantity'], $item['unit_symbol']) ?>
                                        <?php if ($item['maximum_order_qty']): ?>
                                            | Max: <?= $fmtQty($item['maximum_order_qty'], $item['unit_symbol']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="delivery_method">Delivery Method</label>
                                    <select class="form-select" id="delivery_method" name="delivery_method" required>
                                        <option value="pickup">Pickup from Farm</option>
                                        <option value="farmer_delivery">Farmer Delivery</option>
                                    </select>
                                </div>

                                <div class="col-md-6 pickup-field">
                                    <label class="form-label" for="preferred_pickup_date">Preferred Pickup Date</label>
                                    <input type="date" class="form-control" id="preferred_pickup_date" name="preferred_pickup_date"
                                           min="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-6 pickup-field">
                                    <label class="form-label" for="preferred_pickup_time">Preferred Pickup Time</label>
                                    <input type="time" class="form-control" id="preferred_pickup_time" name="preferred_pickup_time">
                                </div>
                                <div class="col-12 delivery-field" style="display: none;">
                                    <label class="form-label" for="delivery_address">Delivery Address</label>
                                    <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2"
                                              maxlength="<?= FIELD_LIMITS['delivery_address'] ?>"
                                              placeholder="Full address including barangay, city/municipality, province"></textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="buyer_note">Note for Farmer (optional)</label>
                                    <textarea class="form-control" id="buyer_note" name="buyer_note" rows="2"
                                              maxlength="<?= FIELD_LIMITS['buyer_note'] ?>"
                                              placeholder="Special instructions, packaging preferences, etc."></textarea>
                                </div>

                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="bi bi-cart-plus me-1"></i>Submit Order Request
                                    </button>
                                    <a href="browse.php" class="btn btn-outline">Continue Shopping</a>
                                </div>
                            </form>
                        </div>
                    <?php elseif (current_user_id() && current_user_role() === 'farmer'): ?>
                        <p class="text-muted">You cannot order your own produce.</p>
                    <?php elseif (current_user_id()): ?>
                        <p class="text-muted">Ordering is available for buyers only.</p>
                    <?php else: ?>
                        <p><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Sign in to place your order</p>
                        <a class="btn btn-primary" href="<?php echo e(login_url('listing.php?id=' . (int) $id)); ?>"><?= $l($copy['listing'], 'login_to_order') ?></a>
                    <?php endif; ?>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const method = document.getElementById('delivery_method');
                    if (!method) return;
                    const pickupFields = document.querySelectorAll('.pickup-field');
                    const deliveryFields = document.querySelectorAll('.delivery-field');
                    function toggle() {
                        const isDelivery = method.value === 'farmer_delivery';
                        pickupFields.forEach(f => f.style.display = isDelivery ? 'none' : 'block');
                        deliveryFields.forEach(f => f.style.display = isDelivery ? 'block' : 'none');
                    }
                    method.addEventListener('change', toggle);
                    toggle();
                });
                </script>
                <script>
                // Sync Add to Cart / Buy Now qty with order form
                document.addEventListener('DOMContentLoaded', function () {
                    const qty = document.getElementById('quantity');
                    if (!qty) return;
                    const sync = function () {
                        var v = qty.value;
                        document.querySelectorAll('.listing-cta [name="quantity"]').forEach(function (h) { h.value = v; });
                    };
                    qty.addEventListener('input', sync);
                });
                </script>
            </div>
        </div>
    </div>
<?php if (!$sidebarShell): ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<?php else: ?>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
<?php endif; ?>