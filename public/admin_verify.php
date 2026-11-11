<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin', 'manager');

$pdo = DB::pdo();

$idLabels = [
    'national_id'       => 'National ID',
    'drivers_license'   => "Driver's License",
    'passport'          => 'Passport',
    'voters_id'         => "Voter's ID",
    'postal_id'         => 'Postal ID',
    'senior_citizen_id' => 'Senior Citizen ID',
    'pwd_id'            => 'PWD ID',
];

// Decide a pending application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
$i18nAll = include __DIR__ . '/../src/i18n_copy.php';
$ordersCopy = $i18nAll['orders'] ?? [];
$listingActionsCopy = $i18nAll['listing_actions'] ?? [];
$adminVerifyCopy = $i18nAll['admin_verify'] ?? [];
$cartCopy = $i18nAll['cart'] ?? [];

    $action = $_POST['action'] ?? '';
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $uid    = (int) ($_POST['user_id'] ?? 0);

    if ($uid && in_array($action, ['approve', 'reject'], true)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT u.status AS user_status, fp.verification_status
                                   FROM farmer_profiles fp
                                   JOIN users u ON u.id = fp.user_id
                                   WHERE fp.user_id = :id FOR UPDATE');
            $stmt->execute(['id' => $uid]);
            $row = $stmt->fetch();

            if (!$row) {
                throw new UserFacingException(t($adminVerifyCopy, 'not_found'));
            }

            $flash = farmer_review_apply($pdo, $row, $uid, $action, $reason, current_user_id(), $adminVerifyCopy);

            $pdo->commit();
            flash_set('success', $flash);
        } catch (UserFacingException $e) {
            $pdo->rollBack();
            flash_set('error', $e->getMessage());
        } catch (Throwable $e) {
            // Unexpected: its text can name tables, columns or SQL, so log it
            // and show only a reference the user can quote to support.
            $pdo->rollBack();
            flash_set('error', generic_error_message(app_log_exception($e, 'admin_verify')));
        }
    } else {
        flash_set('error', t($adminVerifyCopy, 'invalid_request'));
    }
    redirect_to('admin_verify.php?status=pending');
}

// Filters
$filter = $_GET['status'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $filter = 'pending';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

// Farmer applications
$sql = 'SELECT u.id AS user_id, u.email, u.status AS user_status, u.created_at AS registered_at,
               fp.farm_name, fp.contact_person, fp.contact_phone, fp.contact_email,
               fp.barangay, fp.municipality_city, fp.province,
               fp.valid_id_type, fp.valid_id_number,
               fp.verification_status, fp.rejection_reason, fp.verified_at, fp.verified_by,
               au.email AS reviewer_email
        FROM farmer_profiles fp
        JOIN users u ON u.id = fp.user_id
        LEFT JOIN users au ON au.id = fp.verified_by';
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE fp.verification_status = :status';
    $params['status'] = $filter;
}

// Total count for pagination
$countSql = 'SELECT COUNT(*) FROM farmer_profiles fp JOIN users u ON u.id = fp.user_id';
if ($filter !== 'all') {
    $countSql .= ' WHERE fp.verification_status = :status';
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql .= ' ORDER BY u.created_at ASC LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->execute();
$apps = $stmt->fetchAll();

$counts = [
    'pending'  => (int) $pdo->query('SELECT COUNT(*) FROM farmer_profiles WHERE verification_status = \'pending\'')->fetchColumn(),
    'approved' => (int) $pdo->query('SELECT COUNT(*) FROM farmer_profiles WHERE verification_status = \'approved\'')->fetchColumn(),
    'rejected' => (int) $pdo->query('SELECT COUNT(*) FROM farmer_profiles WHERE verification_status = \'rejected\'')->fetchColumn(),
    'all'      => (int) $pdo->query('SELECT COUNT(*) FROM farmer_profiles')->fetchColumn(),
];

$pageTitle = 'Farmer Verifications';
$activePage = 'verifications';
require __DIR__ . '/partials/dashboard-layout.php';
?>

<div class="dash-section-header d-flex flex-wrap justify-content-between align-items-end gap-2">
    <div>
        <h1>Farmer Verifications</h1>
        <p>Review farm applications before their listings go live on the marketplace.</p>
    </div>
</div>

<div class="mb-4" data-reveal="stagger">
    <?php
    $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
    foreach ($tabs as $key => $label): ?>
        <a href="admin_verify.php?status=<?= e($key) ?>"
           class="btn btn-sm me-2 mb-2 <?= $filter === $key ? 'btn-primary' : 'btn-outline-primary' ?>">
            <?= e($label) ?> (<?= (int) $counts[$key] ?>)
        </a>
    <?php endforeach; ?>
</div>

<?php if ($flash = flash_get('success')): ?>
    <div class="alert alert-success" role="alert"><?= e($flash) ?></div>
<?php elseif ($flash = flash_get('error')): ?>
    <div class="alert alert-danger" role="alert"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($apps): ?>
<div class="table-responsive">
    <table class="dash-table align-middle">
        <thead>
            <tr>
                <th>Farm</th>
                <th>Contact</th>
                <th>Location</th>
                <th>Valid ID</th>
                <th>Registered</th>
                <th>Status</th>
                <th>Reviewed by</th>
                <th class="action-col">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apps as $app): ?>
            <tr id="farmer-<?= (int) $app['user_id'] ?>">
                <td>
                    <div class="fw-medium"><?= e($app['farm_name']) ?></div>
                    <div class="cell-muted small"><?= e($app['email']) ?></div>
                </td>
                <td>
                    <div><?= e($app['contact_person']) ?></div>
                    <div class="cell-muted small">
                        <?= $app['contact_phone'] ? e($app['contact_phone']) : '' ?>
                        <?= $app['contact_email'] ? e($app['contact_email']) : '' ?>
                    </div>
                </td>
                <td class="cell-muted">
                    <?= implode(', ', array_filter([$app['barangay'], $app['municipality_city'], $app['province']])) ?>
                </td>
                <td class="cell-muted">
                    <?= e($idLabels[$app['valid_id_type']] ?? $app['valid_id_type']) ?>
                    <div class="small"><?= e(sensitive_decrypt((string) $app['valid_id_number'])) ?></div>
                </td>
                <td class="cell-muted text-nowrap"><?= e(date('M j, Y', strtotime($app['registered_at']))) ?></td>
                <td><?= status_badge($app['verification_status']) ?></td>
                <td class="cell-muted">
                    <?php if ($app['verified_at']): ?>
                        <?= e($app['reviewer_email'] ?? '—') ?>
                        <div class="small"><?= e(date('M j, Y g:i A', strtotime($app['verified_at']))) ?></div>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                    <?php if ($app['rejection_reason']): ?>
                        <div class="small text-danger mt-1" title="Rejection reason"><?= e($app['rejection_reason']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="action-col">
                    <?php if ($app['verification_status'] === 'pending'): ?>
                    <form method="post" action="admin_verify.php" class="d-flex flex-wrap align-items-center gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $app['user_id'] ?>">
                        <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 190px;"
                               maxlength="<?= FIELD_LIMITS['reason'] ?>"
                               placeholder="Reject reason" aria-label="Reject reason">
                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-primary">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                    </form>
                    <?php else: ?>
                        <span class="cell-muted small">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <?php
    $paramParts = ['status=' . urlencode($filter)];
    $param = implode('&', $paramParts);
    include __DIR__ . '/partials/pager.php';
    ?>
<?php endif; ?>

<?php else: ?>
<div class="empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon">
        <path d="M8.934.607a2 2 0 0 0-1.868 0L1.282 3.066A2 2 0 0 0 0 4.86V8a2 2 0 0 0 1 1.732v.268a2 2 0 0 0 1 1.732l6 3.464a2 2 0 0 0 2 0l6-3.464a2 2 0 0 0 1-1.732V6.732A2 2 0 0 0 16 4.86L8.934.607zM8 1.309l6 3.464v.268L8 1.607 2.5 4.62 8 7.59l5.5-2.97 6 3.464A1 1 0 0 1 16 8.607V12a1 1 0 0 1-2 0V8.607l-6 3.463-6-3.463V4.86l6-3.55z"/>
    </svg>
    <h2 class="empty-state-title">No <?= e($filter) ?> farmer applications</h2>
    <p class="empty-state-description">Nothing to show with the current filter.</p>
    <a href="admin_verify.php?status=pending" class="btn btn-primary">View pending</a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>