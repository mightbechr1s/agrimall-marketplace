<?php
require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

$user = api_require_auth();
$userId = (int) $user['id'];
$role = $user['role'];

$input = api_input();
$orderId = (int) ($input['order_id'] ?? 0);
$action = trim((string) ($input['action'] ?? ''));
$reason = trim((string) ($input['reason'] ?? ''));

if ($orderId <= 0 || $action === '') {
    api_error('Order and action are required.');
}
if (mb_strlen($reason) > FIELD_LIMITS['reason']) {
    api_error(sprintf('Reason must be at most %d characters.', FIELD_LIMITS['reason']));
}

$i18nAll = include __DIR__ . '/../../../src/i18n_copy.php';
$ordersCopy = $i18nAll['orders'] ?? [];

$pdo = DB::pdo();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new UserFacingException('Order not found.');
    }

    $isBuyer = $role === 'buyer' && (int) $order['buyer_id'] === $userId;
    $isFarmer = $role === 'farmer' && (int) $order['farmer_id'] === $userId;
    $isStaff = in_array($role, ['admin', 'manager'], true);
    if (!$isBuyer && !$isFarmer && !$isStaff) {
        api_error('Forbidden.', 403);
    }

    $result = order_apply_action($pdo, $order, $action, $role, $userId, $input, $reason, $ordersCopy);

    order_log_status($pdo, $orderId, $result['old'], $result['status'], $userId, $role, $result['reason']);

    if ($isStaff || in_array($action, ['confirm', 'reject', 'cancel'], true)) {
        audit_log('order_' . $action, 'order', $orderId,
            ['status' => $result['old']], ['status' => $result['status']]);
    }

    $pdo->commit();

    api_ok([
        'status' => $result['status'],
        'message' => 'Order updated.',
    ]);
} catch (UserFacingException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('api order action: ' . $e->getMessage());
    api_error('Could not update the order.', 500);
}
