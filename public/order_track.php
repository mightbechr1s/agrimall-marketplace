<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_role('buyer', 'admin', 'manager');

$orderId = (int) ($_GET['id'] ?? 0);
if (!$orderId) {
    flash_set('error', 'Invalid order.');
    redirect_to('orders.php');
}

$pdo = DB::pdo();
$userId = current_user_id();
$role = current_user_role();

$stmt = $pdo->prepare('SELECT * FROM v_order_summary WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();
if (!$order) {
    flash_set('error', 'Order not found.');
    redirect_to('orders.php');
}

// Access check
if ($role === 'buyer' && (int) $order['buyer_id'] !== $userId) {
    http_response_code(403);
    exit('Forbidden.');
}

// Status history
$histStmt = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = :id ORDER BY created_at ASC');
$histStmt->execute(['id' => $orderId]);
$history = $histStmt->fetchAll();

// Buyer profile for saved address
$profileStmt = $pdo->prepare('SELECT delivery_address, barangay, municipality_city, province, latitude, longitude FROM buyer_profiles WHERE user_id = :u');
$profileStmt->execute(['u' => $userId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$activePage = 'orders';
$pageTitle = 'Track Order';
$sidebarShell = true;
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1>Track Order #<?= e($order['order_number'] ?? $orderId) ?></h1>
            <p>Current status: <strong><?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?></strong></p>
        </div>
        <a href="order.php?id=<?= $orderId ?>" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i> Back to order</a>
<a href="order_track.php?id=<?= $orderId ?>" class="btn btn-outline-primary btn-sm mb-3 ms-2"><i class="bi bi-geo-alt me-1"></i> Track delivery</a>

        <!-- Status timeline -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Order timeline</h5>
                <?php
                $statuses = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'completed'];
                $currentStatus = $order['status'];
                $statusIndex = array_search($currentStatus, $statuses);
                if ($statusIndex === false) $statusIndex = -1;
                $icons = [
                    'pending' => 'bi-clock',
                    'confirmed' => 'bi-check-circle',
                    'preparing' => 'bi-box-seam',
                    'ready_for_pickup' => 'bi-shop',
                    'out_for_delivery' => 'bi-truck',
                    'completed' => 'bi-check-circle-fill',
                ];
                ?>
                <div class="order-timeline">
                    <?php foreach ($statuses as $i => $s): ?>
                    <div class="timeline-step <?= $i <= $statusIndex ? 'is-done' : '' ?> <?= $i === $statusIndex ? 'is-current' : '' ?>">
                        <div class="timeline-icon"><i class="bi <?= $icons[$s] ?>" aria-hidden="true"></i></div>
                        <div class="timeline-label"><?= e(ucfirst(str_replace('_', ' ', $s))) ?></div>
                        <?php
                        // Find the timestamp from history
                        $ts = null;
                        foreach ($history as $h) {
                            if ($h['new_status'] === $s) { $ts = $h['created_at']; break; }
                        }
                        if ($ts): ?>
                        <div class="timeline-time text-muted small"><?= e(date('M j, g:i A', strtotime($ts))) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Map -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Delivery location</h5>
                <div id="delivery-map" style="height: 300px; border-radius: var(--radius-card); overflow: hidden; background: var(--canvas);"></div>
                <p class="text-muted small mt-2 mb-0">
                    <i class="bi bi-geo-alt me-1"></i><?= e($order['delivery_address'] ?: 'No address on file') ?>
                </p>
            </div>
        </div>

        <!-- Saved address -->
        <?php if ($role === 'buyer'): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Saved delivery address</h5>
                <form method="post" action="save_address.php" class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="order_track.php?id=<?= $orderId ?>">
                    <div class="col-12">
                        <label class="form-label" for="delivery_address">Address</label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" maxlength="500"><?= e($profile['delivery_address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="barangay">Barangay</label>
                        <input type="text" class="form-control" id="barangay" name="barangay" value="<?= e($profile['barangay'] ?? '') ?>" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="municipality">City/Municipality</label>
                        <input type="text" class="form-control" id="municipality" name="municipality" value="<?= e($profile['municipality_city'] ?? '') ?>" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="province">Province</label>
                        <input type="text" class="form-control" id="province" name="province" value="<?= e($profile['province'] ?? '') ?>" maxlength="100">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save address</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Leaflet map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
  var map = L.map('delivery-map').setView([14.5995, 120.9842], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 18
  }).addTo(map);
  var addr = <?= json_encode($order['delivery_address'] ?? '') ?>;
  // Geocode via Nominatim (free, no API key)
  if (addr) {
    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(addr + ', Philippines'))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.length) {
          var lat = parseFloat(data[0].lat);
          var lon = parseFloat(data[0].lon);
          L.marker([lat, lon]).addTo(map).bindPopup(addr).openPopup();
          map.setView([lat, lon], 14);
        }
      }).catch(function () {});
  }
})();
</script>
<style>
.order-timeline { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; }
.timeline-step { flex: 1 1 0; min-width: 80px; text-align: center; opacity: 0.4; transition: opacity 0.3s; }
.timeline-step.is-done { opacity: 1; }
.timeline-step.is-current .timeline-icon { background: var(--primary); color: #fff; transform: scale(1.1); }
.timeline-icon {
  width: 2.5rem; height: 2.5rem; border-radius: 50%;
  background: var(--canvas-deep); color: var(--text-soft);
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 1.1rem; margin-bottom: 0.4rem;
  transition: all 0.3s ease;
}
.timeline-step.is-done .timeline-icon { background: var(--primary-tint); color: var(--primary); }
.timeline-label { font-size: 0.78rem; font-weight: 600; color: var(--text); }
.timeline-time { font-size: 0.7rem; margin-top: 0.2rem; }
@media (max-width: 575px) {
  .order-timeline { flex-direction: column; gap: 0.75rem; }
  .timeline-step { display: flex; align-items: center; gap: 0.75rem; text-align: left; min-width: 0; }
  .timeline-icon { margin-bottom: 0; flex-shrink: 0; }
}
</style>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
PHP;
file_put_contents('C:/Users/your4/Projects/animal-marketplace/public/order_track.php', $content);
echo "written\n";
