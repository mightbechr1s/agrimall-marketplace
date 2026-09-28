<?php
require __DIR__ . '/../src/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = DB::pdo()->prepare('SELECT * FROM v_active_listings WHERE id = :id');
$stmt->execute(['id' => $id]);
$item = $stmt->fetch();

$pageTitle = 'Marketplace';
$activeNav = 'browse';
require __DIR__ . '/partials/header.php';

if (!$item) {
    http_response_code(404);
    ?>
    <main class="container py-5">
        <div class="alert alert-warning">This listing is no longer available.</div>
        <a class="btn btn-primary" href="index.php">Back to marketplace</a>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

// Get all images for this listing
$imageStmt = DB::pdo()->prepare('SELECT image_path FROM listing_images WHERE listing_id = :id ORDER BY is_primary DESC, sort_order');
$imageStmt->execute(['id' => $id]);
$images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = $item['name'];
$fmtQty = function ($value, $symbol): string {
    return rtrim(rtrim(number_format((float) $value, 4), '0'), '.') . ' ' . e($symbol);
};
?>

<main class="container py-4">
    <nav class="small mb-3" aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="index.php">Marketplace</a></li>
            <li class="breadcrumb-item"><a href="index.php?category=<?= e($item['category_slug']) ?>"><?= e($item['category_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($item['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-6">
            <?php if (!empty($images)): ?>
                <div id="listingImageCarousel" class="carousel slide mb-3" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php foreach ($images as $index => $imageUrl): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                <img src="<?= e($imageUrl) ?>" class="d-block w-100 rounded-3" style="max-height: 440px; object-fit: cover;" alt="<?= e($item['name']) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#listingImageCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#listingImageCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="placeholder-img rounded-3 d-flex align-items-center justify-content-center" style="height: 440px">
                    <span class="text-white fw-semibold fs-3"><?= e($item['category_name']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-6">
            <div class="mb-2">
                <?php if ((int) $item['is_organic'] === 1): ?>
                    <span class="badge text-bg-accent me-1">Organic</span>
                <?php endif; ?>
                <span class="badge text-bg-light border"><?= e($item['category_name']) ?></span>
            </div>
            <h1 class="fw-bold"><?= e($item['name']) ?></h1>
            <p class="text-muted">
                <?= e($item['farm_name']) ?> &middot; <?= e($item['barangay']) ?>, <?= e($item['municipality_city']) ?>, <?= e($item['province']) ?>
            </p>

            <div class="h3 price mb-1">
                <?= money($item['price_per_unit']) ?> <span class="fs-6 text-muted">/ <?= e($item['unit_symbol']) ?></span>
            </div>
            <div class="small text-muted mb-3">
                <?= $fmtQty($item['available_quantity'], $item['unit_symbol']) ?> available
                &middot; min order <?= $fmtQty($item['minimum_order_qty'], $item['unit_symbol']) ?>
            </div>

            <p><?= nl2br(e($item['description'] ?? '')) ?></p>

            <dl class="row small mb-4">
                <?php if ($item['harvest_date']): ?>
                    <dt class="col-sm-4">Harvest date</dt>
                    <dd class="col-sm-8"><?= e($item['harvest_date']) ?></dd>
                <?php endif; ?>
                <?php if ($item['expiry_date']): ?>
                    <dt class="col-sm-4">Best before</dt>
                    <dd class="col-sm-8"><?= e($item['expiry_date']) ?></dd>
                <?php endif; ?>
                <?php if ($item['maximum_order_qty']): ?>
                    <dt class="col-sm-4">Max order</dt>
                    <dd class="col-sm-8"><?= $fmtQty($item['maximum_order_qty'], $item['unit_symbol']) ?></dd>
                <?php endif; ?>
                <?php if ($item['verification_status'] === 'approved'): ?>
                    <dt class="col-sm-4">Farm status</dt>
                    <dd class="col-sm-8"><span class="badge text-bg-success">Verified farm</span></dd>
                <?php endif; ?>
            </dl>

            <?php if (current_user_id()): ?>
                <div class="alert alert-light border small">
                    Checkout is the next milestone &mdash; your orders will appear in the dashboard.
                </div>
            <?php else: ?>
                <a class="btn btn-primary btn-lg" href="<?php echo e(login_url('listing.php?id=' . (int) ($_GET['id'] ?? 0))); ?>">Log in to place an order</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>