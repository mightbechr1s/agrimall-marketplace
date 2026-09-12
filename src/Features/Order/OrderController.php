<?php
// src/Features/Order/OrderController.php

require_once dirname(__DIR__, 2) . '/Core/bootstrap.php';

class OrderController
{
    /**
     * Create a new order request.
     *
     * Order total is computed at read time:
     * (quantity * unit_price) + IFNULL(delivery_fee, 0).
     */
    public static function create(
        int $buyerId,
        int $listingId,
        float $quantity,
        string $orderMethod
    ): bool {
        $pdo = DB::pdo();

        if ($quantity <= 0) {
            flash('error', 'Quantity must be greater than zero.');
            return false;
        }

        $allowedOrderMethods = [
            'pickup',
            'local_delivery'
        ];

        if (!in_array($orderMethod, $allowedOrderMethods, true)) {
            flash('error', 'Invalid order method.');
            return false;
        }

        /*
         * Get listing information including farm owner.
         */
        $stmt = $pdo->prepare("
            SELECT
                l.id,
                l.farm_id,
                l.name,
                l.quantity_available,
                l.price,
                l.status,
                u.id AS owner_id
            FROM listings l
            INNER JOIN farms f
                ON f.id = l.farm_id
            INNER JOIN users u
                ON u.id = f.user_id
            WHERE l.id = ?
            LIMIT 1
        ");

        $stmt->execute([$listingId]);

        $listing = $stmt->fetch();

        if (!$listing) {
            flash('error', 'The selected listing does not exist.');
            return false;
        }

        if ($listing['status'] !== 'active') {
            flash('error', 'This listing is no longer available.');
            return false;
        }

        if ((float)$listing['quantity_available'] < $quantity) {
            flash('error', 'Not enough stock available.');
            return false;
        }

        if ((int)$listing['owner_id'] === $buyerId) {
            flash('error', 'You cannot order your own listing.');
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO orders
            (
                buyer_id,
                listing_id,
                quantity,
                unit_price,
                order_method,
                status
            )
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $buyerId,
            $listingId,
            $quantity,
            $listing['price'],
            $orderMethod
        ]);

        flash(
            'success',
            'Order request submitted successfully.'
        );

        return true;
    }

    /**
     * Get buyer orders.
     */
    public static function getBuyerOrders(int $buyerId): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT
                o.*,
                (o.quantity * o.unit_price) + IFNULL(o.delivery_fee, 0) AS subtotal,
                l.name AS listing_name,
                l.unit,
                f.farm_name AS farm_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            WHERE o.buyer_id = ?
            ORDER BY o.created_at DESC
        ");

        $stmt->execute([$buyerId]);

        return $stmt->fetchAll();
    }

    /**
     * Get farmer orders.
     */
    public static function getFarmerOrders(int $farmerId): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT
                o.*,
                (o.quantity * o.unit_price) + IFNULL(o.delivery_fee, 0) AS subtotal,
                l.name AS listing_name,
                l.unit,
                u.name AS buyer_name,
                u.email AS buyer_email
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            INNER JOIN users u
                ON u.id = o.buyer_id
            WHERE f.user_id = ?
            ORDER BY o.created_at DESC
        ");

        $stmt->execute([$farmerId]);

        return $stmt->fetchAll();
    }

    /**
     * Get a single order, scoped to the requesting user.
     */
    public static function getById(
        int $orderId,
        int $userId,
        string $role
    ): ?array {
        $pdo = DB::pdo();

        $sql = "
            SELECT
                o.*,
                (o.quantity * o.unit_price) + IFNULL(o.delivery_fee, 0) AS subtotal,
                l.name AS listing_name,
                l.description,
                l.unit,
                f.farm_name AS farm_name,
                f.location AS listing_location,
                u_buyer.name AS buyer_name,
                u_buyer.email AS buyer_email,
                f.farm_name AS seller_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            INNER JOIN users u_buyer
                ON u_buyer.id = o.buyer_id
            WHERE o.id = ?
        ";

        $params = [$orderId];

        if ($role === 'buyer') {
            $sql .= " AND o.buyer_id = ?";
            $params[] = $userId;
        } elseif ($role === 'farmer') {
            $sql .= " AND f.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    /**
     * Farmer accepts pickup order.
     */
    public static function acceptPickup(
        int $orderId,
        int $farmerId
    ): bool {
        $pdo = DB::pdo();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    o.*,
                    l.quantity_available AS stock,
                    l.farm_id
                FROM orders o
                INNER JOIN listings l
                    ON l.id = o.listing_id
                INNER JOIN farms f
                    ON f.id = l.farm_id
                WHERE o.id = ?
                  AND f.user_id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $orderId,
                $farmerId
            ]);

            $order = $stmt->fetch();

            if (!$order) {
                $pdo->rollBack();

                flash('error', 'Order not found.');
                return false;
            }

            if ($order['status'] !== 'pending') {
                $pdo->rollBack();

                flash(
                    'error',
                    'This order cannot be accepted.'
                );

                return false;
            }

            if ($order['order_method'] !== 'pickup') {
                $pdo->rollBack();

                flash(
                    'error',
                    'This action is only for pickup orders.'
                );

                return false;
            }

            if ((float)$order['stock'] < (float)$order['quantity']) {
                $pdo->rollBack();

                flash(
                    'error',
                    'There is not enough stock to fulfill this order.'
                );

                return false;
            }

            /*
             * Deduct stock.
             */
            $stmt = $pdo->prepare("
                UPDATE listings
                SET quantity_available = quantity_available - ?
                WHERE id = ?
            ");

            $stmt->execute([
                $order['quantity'],
                $order['listing_id']
            ]);

            /*
             * Update listing status when stock reaches zero.
             */
            $stmt = $pdo->prepare("
                UPDATE listings
                SET status =
                    CASE
                        WHEN quantity_available <= 0 THEN 'sold_out'
                        ELSE status
                    END
                WHERE id = ?
            ");

            $stmt->execute([
                $order['listing_id']
            ]);

            /*
             * Confirm order.
             */
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'confirmed'
                WHERE id = ?
            ");

            $stmt->execute([
                $orderId
            ]);

            $pdo->commit();

            flash(
                'success',
                'Pickup order accepted and stock has been deducted.'
            );

            return true;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash(
                'error',
                'Unable to accept the order.'
            );

            return false;
        }
    }

    /**
     * Farmer provides a local delivery fee.
     */
    public static function setDeliveryFee(
        int $orderId,
        int $farmerId,
        float $deliveryFee
    ): bool {
        $pdo = DB::pdo();

        if ($deliveryFee < 0) {
            flash(
                'error',
                'Delivery fee cannot be negative.'
            );

            return false;
        }

        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.status,
                o.order_method
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            WHERE o.id = ?
              AND f.user_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $orderId,
            $farmerId
        ]);

        $order = $stmt->fetch();

        if (!$order) {
            flash('error', 'Order not found.');
            return false;
        }

        if ($order['status'] !== 'pending') {
            flash(
                'error',
                'A delivery fee can only be added to a pending order.'
            );

            return false;
        }

        if ($order['order_method'] !== 'local_delivery') {
            flash(
                'error',
                'This order is not a local delivery order.'
            );

            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE orders
            SET
                delivery_fee = ?,
                status = 'delivery_fee_review'
            WHERE id = ?
              AND status = 'pending'
        ");

        $stmt->execute([
            $deliveryFee,
            $orderId
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'The delivery fee could not be saved.'
            );

            return false;
        }

        flash(
            'success',
            'Delivery fee sent to the buyer for review.'
        );

        return true;
    }

    /**
     * Buyer accepts delivery fee.
     * Stock is deducted only after buyer accepts.
     */
    public static function acceptDeliveryFee(
        int $orderId,
        int $buyerId
    ): bool {
        $pdo = DB::pdo();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    o.*,
                    l.quantity_available AS stock
                FROM orders o
                INNER JOIN listings l
                    ON l.id = o.listing_id
                WHERE o.id = ?
                  AND o.buyer_id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $orderId,
                $buyerId
            ]);

            $order = $stmt->fetch();

            if (!$order) {
                $pdo->rollBack();

                flash('error', 'Order not found.');
                return false;
            }

            if ($order['status'] !== 'delivery_fee_review') {
                $pdo->rollBack();

                flash(
                    'error',
                    'This order is not waiting for delivery fee approval.'
                );

                return false;
            }

            if ((float)$order['stock'] < (float)$order['quantity']) {
                $pdo->rollBack();

                flash(
                    'error',
                    'There is not enough stock available.'
                );

                return false;
            }

            /*
             * Deduct stock.
             */
            $stmt = $pdo->prepare("
                UPDATE listings
                SET quantity_available = quantity_available - ?
                WHERE id = ?
            ");

            $stmt->execute([
                $order['quantity'],
                $order['listing_id']
            ]);

            /*
             * Mark sold out when stock reaches zero.
             */
            $stmt = $pdo->prepare("
                UPDATE listings
                SET status =
                    CASE
                        WHEN quantity_available <= 0 THEN 'sold_out'
                        ELSE status
                    END
                WHERE id = ?
            ");

            $stmt->execute([
                $order['listing_id']
            ]);

            /*
             * Confirm order.
             */
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'confirmed'
                WHERE id = ?
                  AND buyer_id = ?
            ");

            $stmt->execute([
                $orderId,
                $buyerId
            ]);

            $pdo->commit();

            flash(
                'success',
                'Delivery fee accepted. Order confirmed.'
            );

            return true;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash(
                'error',
                'Unable to accept the delivery fee.'
            );

            return false;
        }
    }

    /**
     * Buyer declines delivery fee.
     */
    public static function declineDeliveryFee(
        int $orderId,
        int $buyerId
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = 'rejected'
            WHERE id = ?
              AND buyer_id = ?
              AND status = 'delivery_fee_review'
        ");

        $stmt->execute([
            $orderId,
            $buyerId
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'The delivery fee could not be declined.'
            );

            return false;
        }

        flash(
            'success',
            'Delivery fee declined. The order was rejected.'
        );

        return true;
    }

    /**
     * Farmer changes confirmed order to preparing.
     */
    public static function startPreparing(
        int $orderId,
        int $farmerId
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            UPDATE orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            SET o.status = 'preparing'
            WHERE o.id = ?
              AND f.user_id = ?
              AND o.status = 'confirmed'
        ");

        $stmt->execute([
            $orderId,
            $farmerId
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Order cannot be moved to preparing.'
            );

            return false;
        }

        flash(
            'success',
            'Order is now being prepared.'
        );

        return true;
    }

    /**
     * Farmer marks pickup order ready.
     */
    public static function markReadyForPickup(
        int $orderId,
        int $farmerId
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            UPDATE orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            SET o.status = 'ready_for_pickup'
            WHERE o.id = ?
              AND f.user_id = ?
              AND o.order_method = 'pickup'
              AND o.status = 'preparing'
        ");

        $stmt->execute([
            $orderId,
            $farmerId
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Order cannot be marked ready for pickup.'
            );

            return false;
        }

        flash(
            'success',
            'Order is ready for pickup.'
        );

        return true;
    }

    /**
     * Farmer marks local delivery order out for delivery.
     */
    public static function markOutForDelivery(
        int $orderId,
        int $farmerId
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            UPDATE orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            SET o.status = 'out_for_delivery'
            WHERE o.id = ?
              AND f.user_id = ?
              AND o.order_method = 'local_delivery'
              AND o.status = 'preparing'
        ");

        $stmt->execute([
            $orderId,
            $farmerId
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Order cannot be marked out for delivery.'
            );

            return false;
        }

        flash(
            'success',
            'Order is now out for delivery.'
        );

        return true;
    }

    /**
     * Complete an order.
     *
     * Buyer can complete pickup.
     * Farmer can complete delivery.
     */
    public static function complete(
        int $orderId,
        int $userId,
        string $role
    ): bool {
        $pdo = DB::pdo();

        if ($role === 'buyer') {

            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'completed'
                WHERE id = ?
                  AND buyer_id = ?
                  AND order_method = 'pickup'
                  AND status = 'ready_for_pickup'
            ");

            $stmt->execute([
                $orderId,
                $userId
            ]);

        } elseif ($role === 'farmer') {

            $stmt = $pdo->prepare("
                UPDATE orders o
                INNER JOIN listings l
                    ON l.id = o.listing_id
                INNER JOIN farms f
                    ON f.id = l.farm_id
                SET o.status = 'completed'
                WHERE o.id = ?
                  AND f.user_id = ?
                  AND o.order_method = 'local_delivery'
                  AND o.status = 'out_for_delivery'
            ");

            $stmt->execute([
                $orderId,
                $userId
            ]);

        } else {
            flash(
                'error',
                'You are not allowed to complete this order.'
            );

            return false;
        }

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Order cannot be completed at this stage.'
            );

            return false;
        }

        flash(
            'success',
            'Order marked as completed.'
        );

        return true;
    }

    /**
     * Buyer cancellation.
     *
     * Buyer can cancel only while pending.
     */
    public static function cancelByBuyer(
        int $orderId,
        int $buyerId
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = 'cancelled'
            WHERE id = ?
              AND buyer_id = ?
              AND status = 'pending'
        ");

        $stmt->execute([
            $orderId,
            $buyerId
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'This order can no longer be cancelled.'
            );

            return false;
        }

        flash(
            'success',
            'Order cancelled successfully.'
        );

        return true;
    }

    /**
     * Farmer cancellation.
     *
     * Farmer can cancel while pending or confirmed.
     */
    public static function cancelByFarmer(
        int $orderId,
        int $farmerId
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.listing_id,
                o.quantity,
                o.status
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            WHERE o.id = ?
              AND f.user_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $orderId,
            $farmerId
        ]);

        $order = $stmt->fetch();

        if (!$order) {
            flash('error', 'Order not found.');
            return false;
        }

        if (!in_array(
            $order['status'],
            ['pending', 'confirmed'],
            true
        )) {
            flash(
                'error',
                'This order can no longer be cancelled by the farmer.'
            );

            return false;
        }

        try {
            $pdo->beginTransaction();

            /*
             * If the order was already confirmed,
             * stock had already been deducted.
             *
             * Return the stock to the listing.
             */
            if ($order['status'] === 'confirmed') {

                $stmt = $pdo->prepare("
                    UPDATE listings
                    SET quantity_available = quantity_available + ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $order['quantity'],
                    $order['listing_id']
                ]);

                /*
                 * If listing was sold out, make it active again.
                 */
                $stmt = $pdo->prepare("
                    UPDATE listings
                    SET status = 'active'
                    WHERE id = ?
                      AND quantity_available > 0
                      AND status = 'sold_out'
                ");

                $stmt->execute([
                    $order['listing_id']
                ]);
            }

            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'cancelled'
                WHERE id = ?
            ");

            $stmt->execute([
                $orderId
            ]);

            $pdo->commit();

            flash(
                'success',
                'Order cancelled successfully.'
            );

            return true;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash(
                'error',
                'Unable to cancel the order.'
            );

            return false;
        }
    }

    /**
     * Admin cancellation / hold.
     */
    public static function adminCancel(
        int $orderId
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT
                id,
                listing_id,
                quantity,
                status
            FROM orders
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $orderId
        ]);

        $order = $stmt->fetch();

        if (!$order) {
            flash('error', 'Order not found.');
            return false;
        }

        try {
            $pdo->beginTransaction();

            /*
             * Restore stock if it was already deducted.
             */
            $stockDeductedStatuses = [
                'confirmed',
                'preparing',
                'ready_for_pickup',
                'out_for_delivery'
            ];

            if (in_array(
                $order['status'],
                $stockDeductedStatuses,
                true
            )) {

                $stmt = $pdo->prepare("
                    UPDATE listings
                    SET quantity_available = quantity_available + ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $order['quantity'],
                    $order['listing_id']
                ]);

                $stmt = $pdo->prepare("
                    UPDATE listings
                    SET status = 'active'
                    WHERE id = ?
                      AND quantity_available > 0
                      AND status = 'sold_out'
                ");

                $stmt->execute([
                    $order['listing_id']
                ]);
            }

            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'cancelled'
                WHERE id = ?
            ");

            $stmt->execute([
                $orderId
            ]);

            $pdo->commit();

            flash(
                'success',
                'Order cancelled by administrator.'
            );

            return true;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash(
                'error',
                'Unable to cancel the order.'
            );

            return false;
        }
    }

    /**
     * Admin hold.
     */
    public static function adminHold(
        int $orderId
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = 'on_hold'
            WHERE id = ?
              AND status NOT IN ('completed', 'cancelled', 'rejected')
        ");

        $stmt->execute([$orderId]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Order cannot be put on hold.'
            );

            return false;
        }

        flash(
            'success',
            'Order placed on hold.'
        );

        return true;
    }

    /**
     * Count orders by status.
     */
    public static function countByStatus(): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->query("
            SELECT
                status,
                COUNT(*) AS total
            FROM orders
            GROUP BY status
        ");

        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int)$row['total'];
        }

        return $result;
    }

    /**
     * Get all orders for administrators.
     */
    public static function getAllOrders(): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->query("
            SELECT
                o.*,
                (o.quantity * o.unit_price) + IFNULL(o.delivery_fee, 0) AS subtotal,
                l.name AS listing_name,
                u_buyer.name AS buyer_name,
                f.farm_name AS farm_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            INNER JOIN users u_buyer
                ON u_buyer.id = o.buyer_id
            ORDER BY o.created_at DESC
        ");

        return $stmt->fetchAll();
    }
}