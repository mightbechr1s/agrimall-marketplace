<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
$i18nAll = include __DIR__ . '/../src/i18n_copy.php';
$ordersCopy = $i18nAll['orders'] ?? [];

$orderId = (int) ($_POST['order_id'] ?? 0);
$action  = $_POST['action'] ?? '';
$reason  = trim((string) ($_POST['reason'] ?? ''));

if (!$orderId || !$action) {
    flash_set('error', t($ordersCopy, 'invalid_request'));
    redirect_to('/dashboard.php');
}

if (mb_strlen($reason) > FIELD_LIMITS['reason']) {
    flash_set('error', sprintf(t($ordersCopy, 'reason_too_long'), FIELD_LIMITS['reason']));
    redirect_to('order.php?id=' . $orderId);
}

$pdo = DB::pdo();
$userId = current_user_id();
$role   = current_user_role();

try {
    $pdo->beginTransaction();

    // Lock the order row
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new UserFacingException(t($ordersCopy, 'order_not_found'));
    }

    $result = order_apply_action($pdo, $order, $action, $role, $userId, $_POST, $reason, $ordersCopy);
    $oldStatus = $result['old'];
    $newStatus = $result['status'];
    $logReason = $result['reason'];

    // Log status transition
    order_log_status($pdo, $orderId, $oldStatus, $newStatus, $userId, $role, $logReason);

    // Admin audit for sensitive actions
    if (in_array($role, ['admin', 'manager'], true) || in_array($action, ['confirm', 'reject', 'cancel'], true)) {
        audit_log('order_' . $action, 'order', $orderId,
            ['status' => $oldStatus], ['status' => $newStatus]);
    }

    $pdo->commit();
    flash_set('success', sprintf(t($ordersCopy, 'status_updated'), $newStatus));
    redirect_to('/order.php?id=' . $orderId);

} catch (UserFacingException $e) {
    $pdo->rollBack();
    flash_set('error', $e->getMessage());
    redirect_to('/order.php?id=' . $orderId);
} catch (Throwable $e) {
    // Unexpected: its text can name tables, columns or SQL, so log it and
    // show only a reference the user can quote to support.
    $pdo->rollBack();
    flash_set('error', generic_error_message(app_log_exception($e, 'order_action')));
    redirect_to('/order.php?id=' . $orderId);
}
