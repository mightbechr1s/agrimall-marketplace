<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

// Require login and farmer role
require_login();
require_role('farmer');

$errors = [];
$old = [];
$categories = [];
$units = [];

// Get categories and units for the form
try {
    $categories = DB::pdo()->query('SELECT id, name, slug FROM produce_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
    $units = DB::pdo()->query('SELECT id, name, symbol, type FROM quantity_units WHERE is_active = 1 ORDER BY name')->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
    $errors[] = $l($copy['lf'], 'err_load_form');
}

// ?edit=<id> reuses this form to update a listing instead of creating one.
// Scoped to the logged-in farmer so the id cannot be swapped for someone else's.
$editId = (int) ($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = DB::pdo()->prepare('SELECT * FROM produce_listings WHERE id = :id AND farmer_id = :farmer');
    $stmt->execute(['id' => $editId, 'farmer' => (int) $_SESSION['user_id']]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        http_response_code(404);
        exit('Listing not found.');
    }
    // Prefill from the row; after a failed POST $old already holds the user's input.
    if (!$old) {
        $old = $editing;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    $old = $_POST;

    // Validate inputs
    $farmerId = (int) $_SESSION['user_id'];

    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $unitId = (int) ($_POST['unit_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $pricePerUnit = number_in($_POST['price_per_unit'] ?? null, 0.01, MAX_DELIVERY_FEE);
    $availableQuantity = number_in($_POST['available_quantity'] ?? null, 0, MAX_QUANTITY);
    $minimumOrderQty = number_in($_POST['minimum_order_qty'] ?? null, 0.0001, MAX_QUANTITY);
    $maxQtyRaw = $_POST['maximum_order_qty'] ?? '';
    $maximumOrderQty = $maxQtyRaw !== '' ? number_in($maxQtyRaw, 0.0001, MAX_QUANTITY) : null;
    $maxQtyInvalid = $maxQtyRaw !== '' && $maximumOrderQty === null;
    $harvestDate = ($_POST['harvest_date'] ?? '') !== '' ? (string) $_POST['harvest_date'] : null;
    $expiryDate = ($_POST['expiry_date'] ?? '') !== '' ? (string) $_POST['expiry_date'] : null;
    $isOrganic = isset($_POST['is_organic']) && $_POST['is_organic'] === '1' ? 1 : 0;

    // Validation
    if ($categoryId <= 0) {
        $errors[] = $l($copy['lf'], 'err_category');
    }

    if ($unitId <= 0) {
        $errors[] = $l($copy['lf'], 'err_unit');
    }

    if ($name === '') {
        $errors[] = $l($copy['lf'], 'err_name');
    }

    if ($pricePerUnit === null) {
        $errors[] = $l($copy['lf'], 'err_price');
    }

    if ($availableQuantity === null) {
        $errors[] = $l($copy['lf'], 'err_qty');
    }

    if ($minimumOrderQty === null) {
        $errors[] = $l($copy['lf'], 'err_min_qty');
    }

    if ($maxQtyInvalid || ($maximumOrderQty !== null && $maximumOrderQty < $minimumOrderQty)) {
        $errors[] = $l($copy['lf'], 'err_max_qty');
    }

    foreach (over_limit_fields($_POST) as $field => $max) {
        $errors[] = sprintf($l($copy['lf'], 'err_too_long'), $max);
    }

    // Validate dates. The "not in the past" rules are creation rules: an existing
    // listing legitimately carries a past harvest date, so enforcing them on edit
    // would make every older listing impossible to save.
    if (!$editing && $harvestDate !== null) {
        if (strtotime($harvestDate) < strtotime('today')) {
            $errors[] = $l($copy['lf'], 'err_harvest_past');
        }
    }
    if ($expiryDate !== null) {
        if (!$editing && strtotime($expiryDate) < strtotime('today')) {
            $errors[] = $l($copy['lf'], 'err_expiry_past');
        }
        if ($harvestDate !== null && strtotime($expiryDate) < strtotime($harvestDate)) {
            $errors[] = $l($copy['lf'], 'err_expiry_before_harvest');
        }
    }

    // Process image upload if provided
    $primaryImage = null;
    if (!empty($_FILES['primary_image']['name']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
        // Handle image upload
        $file = $_FILES['primary_image'];

        // Validate file type
        $imageCheck = validate_uploaded_image($file);
        $mimeType = $imageCheck['mime'] ?? '';
        if (!$imageCheck['ok']) {
            $errors[] = $l($copy['lf'], 'err_img_primary_type');
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $errors[] = $l($copy['lf'], 'err_img_primary_large');
        } else {
            // Generate unique filename
            $fileExtension = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'bin'
            };
            $filename = uniqid('listing_', true) . '.' . $fileExtension;
            $uploadPath = listing_upload_dir() . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $primaryImage = '/uploads/listings/' . $filename;
            } else {
                $errors[] = $l($copy['lf'], 'err_upload_primary');
            }
        }
    }

    // Process gallery images
    $galleryImages = [];
    if (!empty($_FILES['gallery_images']['name'][0]) && $_FILES['gallery_images']['error'][0] === UPLOAD_ERR_OK) {
        // Loop through each file
        $fileCount = count($_FILES['gallery_images']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['gallery_images']['name'][$i],
                    'type' => $_FILES['gallery_images']['type'][$i],
                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                    'error' => $_FILES['gallery_images']['error'][$i],
                    'size' => $_FILES['gallery_images']['size'][$i]
                ];

                // Validate file type
                $imageCheck = validate_uploaded_image($file);
                $mimeType = $imageCheck['mime'] ?? '';
                if (!$imageCheck['ok']) {
                    $errors[] = $l($copy['lf'], 'err_img_gallery_type');
                } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
                    $errors[] = $l($copy['lf'], 'err_img_gallery_large');
                } else {
                    // Generate unique filename
                    $fileExtension = match($mimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'bin'
                    };
                    $filename = uniqid('listing_gallery_', true) . '.' . $fileExtension;
                    $uploadPath = listing_upload_dir() . '/' . $filename;

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $galleryImages[] = '/uploads/listings/' . $filename;
                    } else {
                        $errors[] = $l($copy['lf'], 'err_upload_gallery');
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($editing) {
                // Edit: available_quantity is deliberately absent here. It moves
                // through stock_apply() below so the ledger stays in step
                // (FR-LIST-029/030) instead of being silently overwritten.
                $stmt = DB::pdo()->prepare(
                    'UPDATE produce_listings
                        SET category_id = :category_id, unit_id = :unit_id, name = :name,
                            description = :description, price_per_unit = :price_per_unit,
                            minimum_order_qty = :minimum_order_qty, maximum_order_qty = :maximum_order_qty,
                            harvest_date = :harvest_date, expiry_date = :expiry_date, is_organic = :is_organic
                      WHERE id = :id AND farmer_id = :farmer_id'
                );
                $stmt->execute([
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'name' => $name,
                    'description' => $description,
                    'price_per_unit' => $pricePerUnit,
                    'minimum_order_qty' => $minimumOrderQty,
                    'maximum_order_qty' => $maximumOrderQty,
                    'harvest_date' => $harvestDate,
                    'expiry_date' => $expiryDate,
                    'is_organic' => $isOrganic,
                    'id' => $editId,
                    'farmer_id' => $farmerId,
                ]);

                $listingId = $editId;

                // Re-read the live quantity: an order may have landed since the
                // form was rendered, and the delta must be against the real value.
                $q = DB::pdo()->prepare('SELECT available_quantity FROM produce_listings WHERE id = :id');
                $q->execute(['id' => $listingId]);
                $before = (float) $q->fetchColumn();
                $delta = round($availableQuantity - $before, 4);
                if ($delta !== 0.0) {
                    // shortcut: stock_adjustments has no farmer self-correction
                    // type, so increases log as restock and decreases as spoilage.
                    // Nothing reads adjustment_type yet; add a value if it is ever
                    // surfaced in reporting.
                    stock_apply(DB::pdo(), $listingId, $delta,
                        $delta > 0 ? 'restock' : 'spoilage', null, $farmerId,
                        'Quantity corrected from ' . qty_str($before) . ' to ' . qty_str($availableQuantity) . ' via listing edit.');
                }
            } else {
                // Insert listing
                $stmt = DB::pdo()->prepare(
                    'INSERT INTO produce_listings
                        (farmer_id, category_id, unit_id, name, description, price_per_unit,
                         available_quantity, minimum_order_qty, maximum_order_qty,
                         harvest_date, expiry_date, is_organic, status, published_at)
                     VALUES
                        (:farmer_id, :category_id, :unit_id, :name, :description, :price_per_unit,
                         :available_quantity, :minimum_order_qty, :maximum_order_qty,
                         :harvest_date, :expiry_date, :is_organic, :status, NOW())'
                );
                $stmt->execute([
                    'farmer_id' => $farmerId,
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'name' => $name,
                    'description' => $description,
                    'price_per_unit' => $pricePerUnit,
                    'available_quantity' => $availableQuantity,
                    'minimum_order_qty' => $minimumOrderQty,
                    'maximum_order_qty' => $maximumOrderQty,
                    'harvest_date' => $harvestDate,
                    'expiry_date' => $expiryDate,
                    'is_organic' => $isOrganic,
                    'status' => 'active'
                ]);

                $listingId = (int) DB::pdo()->lastInsertId();

                // Record opening quantity for stock history (FR-LIST-029).
                DB::pdo()->prepare(
                    'INSERT INTO stock_adjustments
                        (listing_id, adjustment_type, quantity_change, quantity_before, quantity_after, reference_type, reference_id, notes, created_by)
                     VALUES
                        (:listing_id, :type, :change, :before, :after, :ref_type, :ref_id, :notes, :created_by)'
                )->execute([
                    'listing_id' => $listingId,
                    'type'       => 'initial',
                    'change'     => $availableQuantity,
                    'before'     => 0,
                    'after'      => $availableQuantity,
                    'ref_type'   => 'manual',
                    'ref_id'     => null,
                    'notes'      => 'Opening quantity when the listing was created.',
                    'created_by' => $farmerId,
                ]);
            }

            // If we have a primary image, add it to listing_images
            if ($primaryImage) {
                // Only one row may be primary; on edit demote the previous one.
                if ($editing) {
                    DB::pdo()->prepare('UPDATE listing_images SET is_primary = 0 WHERE listing_id = :id')
                        ->execute(['id' => $listingId]);
                }
                $imageStmt = DB::pdo()->prepare(
                    'INSERT INTO listing_images (listing_id, image_path, is_primary, sort_order)
                     VALUES (:listing_id, :image_path, 1, 0)'
                );
                $imageStmt->execute([
                    'listing_id' => $listingId,
                    'image_path' => $primaryImage
                ]);
            }

            // If we have gallery images, add them to listing_images
            if (!empty($galleryImages)) {
                foreach ($galleryImages as $index => $imagePath) {
                    $imageStmt = DB::pdo()->prepare(
                        'INSERT INTO listing_images (listing_id, image_path, is_primary, sort_order)
                         VALUES (:listing_id, :image_path, 0, :sort_order)'
                    );
                    $imageStmt->execute([
                        'listing_id' => $listingId,
                        'image_path' => $imagePath,
                        'sort_order' => $index + 1 // Start at 1
                    ]);
                }
            }

            flash_set('success', $l($copy['lf'], $editing ? 'edit_success' : 'submit_success'));
            redirect_to('dashboard.php');
            exit;
        } catch (Exception $e) {
            error_log($e->getMessage());
            $errors[] = $l($copy['lf'], 'err_gen');
        }
    }
}

$pageTitle = $l($copy['lf'], $editing ? 'edit_title' : 'title');
$activePage = 'create_listing';
require __DIR__ . '/partials/dashboard-layout.php';
?>

<div class="dash-panel">
    <div class="dash-panel-header">
        <h1><?= e($pageTitle) ?></h1>
        <div class="d-flex gap-2">
            <?php if ($editing && in_array($editing['status'], ['active', 'paused'], true)): ?>
                <?php $pausing = $editing['status'] === 'active'; ?>
                <form method="post" action="listing_action.php" class="mb-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="listing_id" value="<?= (int) $editId ?>">
                    <input type="hidden" name="return_to" value="create_listing.php?edit=<?= (int) $editId ?>">
                    <button type="submit" id="listing-status-toggle" name="action" value="<?= $pausing ? 'pause' : 'resume' ?>" class="btn btn-outline btn-sm">
                        <i class="bi bi-<?= $pausing ? 'pause' : 'play' ?>-fill me-1"></i><?= $l($copy['lf'], $pausing ? 'pause' : 'resume') ?>
                    </button>
                </form>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
        </div>
    </div>
        <div style="padding: 32px;">
            <?php if ($errors): ?>
                <div style="background: var(--danger-soft); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-sm); font-size: 14px; margin-bottom: 24px; font-weight: 500;">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    <ul style="margin: 0; padding-left: 18px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="create_listing.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <?php if ($editing): ?>
                    <input type="hidden" name="edit_id" value="<?= (int) $editId ?>">
                <?php endif; ?>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:28px;">
                    <div>
                        <label class="form-label" for="category_id"><?= $l($copy['lf'], 'category') ?></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value=""><?= $l($copy['lf'], 'select_category') ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category['id']) ?>"
                                    <?= (!empty($old['category_id']) && $old['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="unit_id"><?= $l($copy['lf'], 'unit') ?></label>
                        <select class="form-select" id="unit_id" name="unit_id" required>
                            <option value=""><?= $l($copy['lf'], 'select_unit') ?></option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= e($unit['id']) ?>"
                                    <?= (!empty($old['unit_id']) && $old['unit_id'] == $unit['id']) ? 'selected' : '' ?>>
                                    <?= e($unit['name']) . ' (' . e($unit['symbol']) . ')' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="name"><?= $l($copy['lf'], 'name') ?></label>
                        <input class="form-control" type="text" id="name" name="name"
                               value="<?= e($old['name'] ?? '') ?>" required maxlength="<?= FIELD_LIMITS['name'] ?>" placeholder="Fresh organic tomatoes" />
                    </div>

                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="description"><?= $l($copy['lf'], 'description') ?></label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="3" maxlength="<?= FIELD_LIMITS['description'] ?>" placeholder="Describe your harvest..."><?= e($old['description'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="form-label" for="price_per_unit"><?= $l($copy['lf'], 'price') ?></label>
                        <input class="form-control" type="number" id="price_per_unit" name="price_per_unit"
                               step="0.01" min="0.01" value="<?= e($old['price_per_unit'] ?? '') ?>" required placeholder="0.00" />
                    </div>

                    <div>
                        <label class="form-label" for="available_quantity"><?= $l($copy['lf'], 'qty') ?></label>
                        <input class="form-control" type="number" id="available_quantity" name="available_quantity"
                               step="0.0001" min="0" value="<?= e($old['available_quantity'] ?? '') ?>" required placeholder="0" />
                    </div>

                    <div>
                        <label class="form-label" for="minimum_order_qty"><?= $l($copy['lf'], 'min_qty') ?></label>
                        <input class="form-control" type="number" id="minimum_order_qty" name="minimum_order_qty"
                               step="0.0001" min="0.0001" value="<?= e($old['minimum_order_qty'] ?? '') ?>" required />
                    </div>

                    <div>
                        <label class="form-label" for="maximum_order_qty"><?= $l($copy['lf'], 'max_qty') ?></label>
                        <input class="form-control" type="number" id="maximum_order_qty" name="maximum_order_qty"
                               step="0.0001" min="0" value="<?= e($old['maximum_order_qty'] ?? '') ?>" />
                    </div>

                    <div>
                        <label class="form-label" for="harvest_date"><?= $l($copy['lf'], 'harvest_date') ?></label>
                        <input class="form-control" type="date" id="harvest_date" name="harvest_date"
                               value="<?= e($old['harvest_date'] ?? '') ?>" />
                    </div>

                    <div>
                        <label class="form-label" for="expiry_date"><?= $l($copy['lf'], 'expiry_date') ?></label>
                        <input class="form-control" type="date" id="expiry_date" name="expiry_date"
                               value="<?= e($old['expiry_date'] ?? '') ?>" />
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:12px 16px; border:1px solid var(--line-soft); border-radius:var(--radius-sm); background:var(--cream); font-size:14px; font-weight:500;">
                            <input type="checkbox" name="is_organic" value="1"
                                   <?= !empty($old['is_organic']) && $old['is_organic'] == '1' ? 'checked' : '' ?>
                                   style="accent-color: var(--green); width:18px; height:18px;">
                            <i class="bi bi-leaf" style="color:var(--green); font-size:16px;"></i>
                            <?= $l($copy['lf'], 'organic') ?>
                        </label>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="primary_image"><?= $l($copy['lf'], 'primary_image') ?></label>
                        <input class="form-control" type="file" id="primary_image" name="primary_image"
                               accept="image/jpeg,image/png,image/webp" />
                        <small style="color:var(--ink-faint); font-size:13px; display:block; margin-top:4px;"><?= $l($copy['lf'], 'img_max') ?></small>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="gallery_images"><?= $l($copy['lf'], 'gallery') ?></label>
                        <input class="form-control" type="file" id="gallery_images" name="gallery_images[]"
                               accept="image/jpeg,image/png,image/webp" multiple />
                        <small style="color:var(--ink-faint); font-size:13px; display:block; margin-top:4px;"><?= $l($copy['lf'], 'gallery_hint') ?></small>
                    </div>
                </div>

                <div style="display:flex; gap:12px; align-items:center;">
                    <button class="btn btn-primary" id="listing-submit" type="submit">
                        <i class="bi bi-<?= $editing ? 'check-lg' : 'plus-circle' ?> me-1"></i> <?= $l($copy['lf'], $editing ? 'save_btn' : 'create_btn') ?>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline" style="font-size:14px; font-weight:500;">
                        <?= $l($copy['lf'], 'cancel') ?>
                    </a>
                </div>
            </form>
        </div>
</div>

<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>