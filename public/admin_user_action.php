<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('admin_reports.php?t=registration');
}

csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$targetId = (int) ($_POST['user_id'] ?? 0);
$action   = trim((string) ($_POST['action'] ?? ''));
$reason   = trim((string) ($_POST['reason'] ?? ''));

if (!$targetId || !in_array($action, ['activate', 'suspend'], true)) {
    flash_set('error', 'Invalid request.');
    redirect_to('admin_reports.php?t=registration');
}

$pdo = DB::pdo();
$adminId = current_user_id();

// Load target
$stmt = $pdo->prepare('SELECT id, email, role, status FROM users WHERE id = :id');
$stmt->execute(['id' => $targetId]);
$target = $stmt->fetch();
if (!$target) {
    flash_set('error', 'User not found.');
    redirect_to('admin_reports.php?t=registration');
}

// Safeguards
if ($targetId === $adminId) {
    flash_set('error', 'You cannot change your own account status.');
    redirect_to('admin_reports.php?t=registration');
}
if ($target['role'] === 'admin' && $action === 'suspend') {
    $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
    if ($activeAdmins <= 1) {
        flash_set('error', 'Cannot suspend the last active administrator.');
        redirect_to('admin_reports.php?t=registration');
    }
}
if ($action === 'suspend' && $reason === '') {
    flash_set('error', 'A reason is required to suspend an account.');
    redirect_to('admin_reports.php?t=registration');
}
if ($action === 'suspend' && mb_strlen($reason) > 500) {
    flash_set('error', 'Reason is too long (max 500 characters).');
    redirect_to('admin_reports.php?t=registration');
}

$newStatus = $action === 'suspend' ? 'suspended' : 'active';
$oldStatus = $target['status'];

if ($oldStatus === $newStatus) {
    flash_set('success', 'No change: user is already ' . $newStatus . '.');
    redirect_to('admin_reports.php?t=registration');
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET status = :s WHERE id = :id')
        ->execute(['s' => $newStatus, 'id' => $targetId]);
    $pdo->prepare(
        'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, reason, old_values, new_values, ip_address, created_at)
         VALUES (:admin, :action, :type, :id, :reason, :old, :new, :ip, NOW())'
    )->execute([
        'admin'  => $adminId,
        'action' => $action === 'suspend' ? 'suspend_user' : 'activate_user',
        'type'   => 'user',
        'id'     => $targetId,
        'reason' => $reason !== '' ? $reason : null,
        'old'    => json_encode(['status' => $oldStatus]),
        'new'    => json_encode(['status' => $newStatus]),
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $pdo->commit();
    flash_set('success', ucfirst($action) . 'd ' . $target['email'] . '.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', generic_error_message(app_log_exception($e, 'admin_user_action')));
}
redirect_to('admin_reports.php?t=registration');
