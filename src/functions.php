/**
 * Cascade effects of suspending a user account (FR-ADM-008/009/010).
 * Caller must have already started a transaction and validated safeguards.
 */
function admin_cascade_suspend(PDO $pdo, int $userId, string $role, ?string $reason): void
{
    $note = $reason !== null && $reason !== '' ? $reason : 'Account suspended by administrator.';

    if ($role === 'farmer') {
        // Move all active/unavailable listings to administrator-deactivated.
        $pdo->prepare("UPDATE produce_listings SET status = 'admin_deactivated'
                       WHERE farmer_id = :id AND status IN ('active','paused','draft','sold_out','out_of_stock')")
            ->execute(['id' => $userId]);

        // Cancel pending / delivery-fee-review requests without changing stock.
        $pending = $pdo->prepare("SELECT id, status FROM orders
                                  WHERE farmer_id = :id AND status IN ('pending','delivery_fee_review')");
        $pending->execute(['id' => $userId]);
        foreach ($pending->fetchAll() as $o) {
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")
                ->execute(['id' => $o['id']]);
            $pdo->prepare("INSERT INTO order_status_history
                (order_id, from_status, to_status, changed_by, changed_by_role, reason, created_at)
                VALUES (:o, :from, 'cancelled', :admin, 'admin', :reason, NOW())")
                ->execute(['o' => $o['id'], 'from' => $o['status'], 'admin' => current_user_id(), 'reason' => $note]);
        }

        // Place confirmed / in-progress orders under administrative hold.
        $pdo->prepare("UPDATE orders SET admin_hold = 1, admin_hold_reason = :r
                       WHERE farmer_id = :id AND status IN ('confirmed','preparing','ready_for_pickup','out_for_delivery')")
            ->execute(['r' => $note, 'id' => $userId]);
    }

    if ($role === 'buyer') {
        // Cancel pending / delivery-fee-review requests without changing stock.
        $pending = $pdo->prepare("SELECT id, status FROM orders
                                  WHERE buyer_id = :id AND status IN ('pending','delivery_fee_review')");
        $pending->execute(['id' => $userId]);
        foreach ($pending->fetchAll() as $o) {
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")
                ->execute(['id' => $o['id']]);
            $pdo->prepare("INSERT INTO order_status_history
                (order_id, from_status, to_status, changed_by, changed_by_role, reason, created_at)
                VALUES (:o, :from, 'cancelled', :admin, 'admin', :reason, NOW())")
                ->execute(['o' => $o['id'], 'from' => $o['status'], 'admin' => current_user_id(), 'reason' => $note]);
        }

        // Place confirmed / in-progress orders under administrative hold.
        $pdo->prepare("UPDATE orders SET admin_hold = 1, admin_hold_reason = :r
                       WHERE buyer_id = :id AND status IN ('confirmed','preparing','ready_for_pickup','out_for_delivery')")
            ->execute(['r' => $note, 'id' => $userId]);
    }
}
