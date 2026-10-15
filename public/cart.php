<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
require_login();
require_role('buyer');

$pageTitle = 'Cart';
$activePage = 'cart';
$role = 'buyer';

$pdo = DB::pdo();
$stmt = $pdo->prepare(
    'SELECT ci.id AS cart_id, ci.quantity, pl.id AS listing_id, pl.name, pl.price_per_unit,
            pl.minimum_order_qty, pl.maximum_order_qty, pl.available_quantity,
            fp.farm_name, qu.symbol,
            (SELECT li.image_path FROM listing_images li
              WHERE li.listing_id = pl.id AND li.is_primary = 1
              ORDER BY li.sort_order LIMIT 1) AS primary_image
     FROM cart_items ci
     JOIN produce_listings pl ON ci.listing_id = pl.id
     JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
     JOIN quantity_units qu ON pl.unit_id = qu.id
     WHERE ci.buyer_id = :buyer
     ORDER BY ci.created_at'
);
$stmt->execute(['buyer' => current_user_id()]);
$items = $stmt->fetchAll();

require __DIR__ . '/partials/dashboard-layout.php';
?>
    <?php if ($flash = flash_get('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>
    <?php if ($flash = flash_get('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="dash-panel mt-2">
        <div class="dash-panel-header">
            <h1><i class="bi bi-basket3 me-2" aria-hidden="true"></i>My Cart</h1>
            <?php if ($items): ?>
                <form method="post" action="cart_update.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Clear Cart</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$items): ?>
            <div class="dash-empty">
                <i class="bi bi-cart3" aria-hidden="true"></i>
                <p>Your cart is empty.</p>
                <a class="btn btn-primary" href="browse.php">Browse Listings</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle cart-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Farm</th>
                            <th style="width:140px;">Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $total = 0.0; ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $subtotal = (float) $item['price_per_unit'] * (float) $item['quantity'];
                            $total += $subtotal;
                            $img = asset($item['primary_image'] ?: fallback_produce_image());
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= e($img) ?>" alt="" width="48" height="48" class="rounded cart-thumb" onerror="this.onerror=null;this.src='assets/images/products/market.jpg'">
                                        <a href="listing.php?id=<?= (int) $item['listing_id'] ?>"><?= e($item['name']) ?></a>
                                    </div>
                                </td>
                                <td class="text-muted small"><?= e($item['farm_name']) ?></td>
                                <td>
                                    <form method="post" action="cart_update.php" class="d-flex gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['cart_id'] ?>">
                                        <input type="number" name="quantity" step="0.01" min="<?= (float) $item['minimum_order_qty'] ?>" max="<?= $item['maximum_order_qty'] ? (float) $item['maximum_order_qty'] : (float) $item['available_quantity'] ?>" value="<?= (float) $item['quantity'] ?>" class="form-control form-control-sm cart-qty-input" aria-label="Quantity for <?= e($item['name']) ?>">
                                        <button class="btn btn-sm btn-outline-primary cart-qty-update" type="submit" aria-label="Update quantity">Update</button>
                                    </form>
                                </td>
                                <td><?= money($item['price_per_unit']) ?> / <?= e($item['symbol']) ?></td>
                                <td class="fw-semibold"><?= money($subtotal) ?></td>
                                <td>
                                    <form method="post" action="cart_update.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['cart_id'] ?>">
                                        <button class="btn btn-sm btn-link cart-remove" type="submit" aria-label="Remove <?= e($item['name']) ?> from cart">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Total:</td>
                            <td class="fw-bold"><?= money($total) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="mb-3">Delivery Details</h5>
                    <form method="post" action="cart_checkout.php" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-4">
                            <label class="form-label" for="delivery_method">Delivery Method</label>
                            <select class="form-select" id="delivery_method" name="delivery_method" required>
                                <option value="pickup">Pickup from Farm</option>
                                <option value="farmer_delivery">Farmer Delivery</option>
                            </select>
                        </div>
                        <div class="col-md-4 pickup-field">
                            <label class="form-label" for="preferred_pickup_date">Preferred Pickup Date</label>
                            <input type="date" class="form-control" id="preferred_pickup_date" name="preferred_pickup_date" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4 pickup-field">
                            <label class="form-label" for="preferred_pickup_time">Preferred Pickup Time</label>
                            <input type="time" class="form-control" id="preferred_pickup_time" name="preferred_pickup_time">
                        </div>
                        <div class="col-12 delivery-field" style="display:none;">
                            <label class="form-label" for="delivery_address">Delivery Address</label>
                            <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" maxlength="<?= FIELD_LIMITS['delivery_address'] ?>" placeholder="Full address including barangay, city/municipality, province"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="buyer_note">Note for Farmer (optional)</label>
                            <textarea class="form-control" id="buyer_note" name="buyer_note" rows="2" maxlength="<?= FIELD_LIMITS['buyer_note'] ?>" placeholder="Special instructions, packaging preferences, etc."></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary btn-lg" type="submit">
                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Place Order(s)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const method = document.getElementById('delivery_method');
        if (!method) return;
        const pickup = document.querySelectorAll('.pickup-field');
        const delivery = document.querySelectorAll('.delivery-field');
        function toggle() {
            const isDelivery = method.value === 'farmer_delivery';
            pickup.forEach((f) => (f.style.display = isDelivery ? 'none' : 'block'));
            delivery.forEach((f) => (f.style.display = isDelivery ? 'block' : 'none'));
        }
        method.addEventListener('change', toggle);
        toggle();
    })();
    </script>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>