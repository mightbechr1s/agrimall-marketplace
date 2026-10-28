<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('admin_reports.php?t=registration');
}

csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$pdo = DB::pdo();
$adminId = current_user_id();

// Support single (user_id) or bulk (user_ids[]) submission.
$ids = [];
if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    foreach ($_POST['user_ids'] as $v) {
        $v = (int) $v;
        if ($v > 0) $ids[] = $v;
    }
} elseif (isset($_POST['user_id'])) {
    $ids[] = (int) $_POST['user_id'];
}
$ids = array_values(array_unique($ids));

$action = trim((string) ($_POST['action'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));

if (!$ids || !in_array($action, ['activate', 'suspend'], true)) {
    flash_set('error', 'Invalid request.');
    redirect_to('admin_reports.php?t=registration');
}

if ($action === 'suspend' && $reason === '') {
    flash_set('error', 'A reason is required to suspend an account.');
    redirect_to('admin_reports.php?t=registration');
}
if ($action === 'suspend' && mb_strlen($reason) > 500) {
    flash_set('error', 'Reason is too long (max 500 characters).');
    redirect_to('admin_reports.php?t=registration');
}

$done = 0;
$errors = [];
foreach ($ids as $targetId) {
    if ($targetId === $adminId) {
        $errors[] = 'Cannot change your own account status.';
        continue;
    }
    $stmt = $pdo->prepare('SELECT id, email, role, status FROM users WHERE id = :id');
    $stmt->execute(['id' => $targetId]);
    $target = $stmt->fetch();
    if (!$target) { $errors[] = "User #$targetId not found."; continue; }

    if ($target['role'] === 'admin' && $action === 'suspend') {
        $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
        if ($activeAdmins <= 1) {
            $errors[] = 'Cannot suspend the last active administrator.';
            continue;
        }
    }

    $newStatus = $action === 'suspend' ? 'suspended' : 'active';
    if ($target['status'] === $newStatus) { continue; }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET status = :s WHERE id = :id')
            ->execute(['s' => $newStatus, 'id' => $targetId]);
        if ($action === 'suspend') {
            admin_cascade_suspend($pdo, $targetId, $target['role'], $reason);
        }
        $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, reason, old_values, new_values, ip_address, created_at)
             VALUES (:admin, :action, :type, :id, :reason, :old, :new, :ip, NOW())'
        )->execute([
            'admin'  => $adminId,
            'action' => $action === 'suspend' ? 'suspend_user' : 'activate_user',
            'type'   => 'user',
            'id'     => $targetId,
            'reason' => $reason !== '' ? $reason : null,
            'old'    => json_encode(['status' => $target['status']]),
            'new'    => json_encode(['status' => $newStatus]),
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $pdo->commit();
        $done++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Failed for ' . $target['email'] . ': ' . generic_error_message(app_log_exception($e, 'admin_user_action'));
    }
}

if ($done > 0) {
    flash_set('success', ucfirst($action) . 'd ' . $done . ' account(s).');
}
if ($errors) {
    flash_set('error', implode(' ', $errors));
}
redirect_to('admin_reports.php?t=registration');
