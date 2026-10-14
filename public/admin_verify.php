<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

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

function v_badge(string $status): string
{
    $map = [
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
    ];
    $variant = $map[$status] ?? 'secondary';
    return '<span class="dash-badge dash-badge--' . $variant . '">' . e($status) . '</span>';
}

// Decide a pending application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

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
                throw new RuntimeException('Farmer application not found.');
            }
            if ($row['verification_status'] !== 'pending') {
                throw new RuntimeException('This application has already been reviewed.');
            }
            if ($action === 'reject' && $reason === '') {
                throw new RuntimeException('A reason is required when rejecting an application.');
            }
            if (mb_strlen($reason) > FIELD_LIMITS['reason']) {
                throw new RuntimeException('Reason is too long (max ' . FIELD_LIMITS['reason'] . ' characters).');
            }

            if ($action === 'approve') {
                $pdo->prepare('UPDATE farmer_profiles
                               SET verification_status = \'approved\', verified_at = NOW(), verified_by = :admin, rejection_reason = NULL
                               WHERE user_id = :id')
                    ->execute(['admin' => current_user_id(), 'id' => $uid]);
                $pdo->prepare('UPDATE users SET status = \'active\' WHERE id = :id')->execute(['id' => $uid]);
                audit_log('verification_approved', 'farmer', $uid,
                    ['verification_status' => 'pending', 'user_status' => $row['user_status']],
                    ['verification_status' => 'approved', 'user_status' => 'active']);
                $flash = 'Farmer application approved.';
            } else {
                $pdo->prepare('UPDATE farmer_profiles
                               SET verification_status = \'rejected\', verified_at = NOW(), verified_by = :admin, rejection_reason = :reason
                               WHERE user_id = :id')
                    ->execute(['admin' => current_user_id(), 'id' => $uid, 'reason' => $reason]);
                audit_log('verification_rejected', 'farmer', $uid,
                    ['verification_status' => 'pending'],
                    ['verification_status' => 'rejected', 'reason' => $reason]);
                $flash = 'Farmer application rejected.';
            }

            $pdo->commit();
            flash_set('success', $flash);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', $e->getMessage());
        }
    } else {
        flash_set('error', 'Invalid request.');
    }
    redirect_to('admin_verify.php?status=pending');
}

// Filters
$filter = $_GET['status'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $filter = 'pending';
}

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
$sql .= ' ORDER BY u.created_at ASC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
                    <div class="small"><?= e($app['valid_id_number']) ?></div>
                </td>
                <td class="cell-muted text-nowrap"><?= e(date('M j, Y', strtotime($app['registered_at']))) ?></td>
                <td><?= v_badge($app['verification_status']) ?></td>
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