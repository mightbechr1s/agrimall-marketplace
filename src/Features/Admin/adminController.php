<?php
// src/Features/Admin/AdminController.php

require_once dirname(__DIR__, 2) . '/Core/bootstrap.php';

class AdminController
{
    /*
     * =========================
     * USER MANAGEMENT
     * =========================
     */

    public static function getUsers(): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                email,
                role,
                phone,
                location,
                is_verified,
                created_at
            FROM users
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll();
    }


    public static function deleteUser(int $userId): bool
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            DELETE FROM users
            WHERE id = ?
              AND role != 'admin'
        ");

        $stmt->execute([$userId]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'User could not be deleted.'
            );

            return false;
        }

        flash(
            'success',
            'User deleted successfully.'
        );

        return true;
    }


    /*
     * =========================
     * FARMER VERIFICATION
     * =========================
     */

    public static function getFarmers(): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                email,
                phone,
                location,
                is_verified,
                created_at
            FROM users
            WHERE role = 'farmer'
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll();
    }


    public static function verifyFarmer(int $farmerId): bool
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            UPDATE users
            SET is_verified = 1
            WHERE id = ?
              AND role = 'farmer'
        ");

        $stmt->execute([$farmerId]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Farmer could not be verified.'
            );

            return false;
        }

        flash(
            'success',
            'Farmer verified successfully.'
        );

        return true;
    }


    public static function rejectFarmer(int $farmerId): bool
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            UPDATE users
            SET is_verified = 0
            WHERE id = ?
              AND role = 'farmer'
        ");

        $stmt->execute([$farmerId]);

        flash(
            'success',
            'Farmer verification was removed.'
        );

        return true;
    }


    /*
     * =========================
     * CATEGORY MANAGEMENT
     * =========================
     */

    public static function getCategories(): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                created_at
            FROM categories
            ORDER BY name ASC
        ");

        return $stmt->fetchAll();
    }


    public static function createCategory(
        string $name
    ): bool {
        $pdo = DB::pdo();

        $name = trim($name);

        if ($name === '') {
            flash(
                'error',
                'Category name is required.'
            );

            return false;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE name = ?
            LIMIT 1
        ");

        $stmt->execute([$name]);

        if ($stmt->fetch()) {
            flash(
                'error',
                'This category already exists.'
            );

            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO categories (name)
            VALUES (?)
        ");

        $stmt->execute([$name]);

        flash(
            'success',
            'Category created successfully.'
        );

        return true;
    }


    public static function deleteCategory(
        int $categoryId
    ): bool {
        $pdo = DB::pdo();

        /*
         * Prevent deleting a category that is
         * currently being used by a listing.
         */
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM listings
            WHERE category_id = ?
        ");

        $stmt->execute([$categoryId]);

        $total = (int)$stmt->fetch()['total'];

        if ($total > 0) {
            flash(
                'error',
                'This category cannot be deleted because it is being used by a listing.'
            );

            return false;
        }

        $stmt = $pdo->prepare("
            DELETE FROM categories
            WHERE id = ?
        ");

        $stmt->execute([$categoryId]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Category could not be deleted.'
            );

            return false;
        }

        flash(
            'success',
            'Category deleted successfully.'
        );

        return true;
    }


    /*
     * =========================
     * LISTING MANAGEMENT
     * =========================
     */

    public static function getListings(): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->query("
            SELECT
                l.*,
                c.name AS category_name,
                u.name AS farmer_name,
                u.email AS farmer_email
            FROM listings l
            INNER JOIN categories c
                ON c.id = l.category_id
            INNER JOIN users u
                ON u.id = l.farmer_id
            ORDER BY l.created_at DESC
        ");

        return $stmt->fetchAll();
    }


    public static function updateListingStatus(
        int $listingId,
        string $status
    ): bool {
        $pdo = DB::pdo();

        $allowedStatuses = [
            'active',
            'sold_out',
            'inactive'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            flash(
                'error',
                'Invalid listing status.'
            );

            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE listings
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $listingId
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Listing status could not be updated.'
            );

            return false;
        }

        flash(
            'success',
            'Listing status updated successfully.'
        );

        return true;
    }


    /*
     * =========================
     * ORDER MANAGEMENT
     * =========================
     */

    public static function getOrders(): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->query("
            SELECT
                o.*,
                l.name AS listing_name,
                u_buyer.name AS buyer_name,
                u_farmer.name AS farmer_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN users u_buyer
                ON u_buyer.id = o.buyer_id
            INNER JOIN users u_farmer
                ON u_farmer.id = o.farmer_id
            ORDER BY o.created_at DESC
        ");

        return $stmt->fetchAll();
    }


    public static function updateOrderStatus(
        int $orderId,
        string $status
    ): bool {
        $pdo = DB::pdo();

        $allowedStatuses = [
            'pending',
            'delivery_fee_review',
            'confirmed',
            'preparing',
            'ready_for_pickup',
            'out_for_delivery',
            'completed',
            'rejected',
            'cancelled'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            flash(
                'error',
                'Invalid order status.'
            );

            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $orderId
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Order status could not be updated.'
            );

            return false;
        }

        flash(
            'success',
            'Order status updated successfully.'
        );

        return true;
    }


    /*
     * =========================
     * ADMIN DASHBOARD
     * =========================
     */

    public static function getDashboardStats(): array
    {
        $pdo = DB::pdo();

        $stats = [];

        $stats['users'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM users
        ")->fetchColumn();

        $stats['farmers'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'farmer'
        ")->fetchColumn();

        $stats['verified_farmers'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'farmer'
              AND is_verified = 1
        ")->fetchColumn();

        $stats['pending_farmers'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'farmer'
              AND is_verified = 0
        ")->fetchColumn();

        $stats['buyers'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'buyer'
        ")->fetchColumn();

        $stats['listings'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
        ")->fetchColumn();

        $stats['active_listings'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE status = 'active'
        ")->fetchColumn();

        $stats['orders'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM orders
        ")->fetchColumn();

        $stats['pending_orders'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM orders
            WHERE status = 'pending'
        ")->fetchColumn();

        $stats['completed_orders'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM orders
            WHERE status = 'completed'
        ")->fetchColumn();

        return $stats;
    }


    /*
     * =========================
     * RECENT ACTIVITY
     * =========================
     */

    public static function getRecentUsers(
        int $limit = 5
    ): array {
        $pdo = DB::pdo();

        $limit = max(1, min($limit, 50));

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                email,
                role,
                is_verified,
                created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll();
    }


    public static function getRecentOrders(
        int $limit = 5
    ): array {
        $pdo = DB::pdo();

        $limit = max(1, min($limit, 50));

        $stmt = $pdo->query("
            SELECT
                o.id,
                o.quantity,
                o.subtotal,
                o.delivery_fee,
                o.status,
                o.fulfillment_type,
                o.created_at,
                l.name AS listing_name,
                u_buyer.name AS buyer_name,
                u_farmer.name AS farmer_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN users u_buyer
                ON u_buyer.id = o.buyer_id
            INNER JOIN users u_farmer
                ON u_farmer.id = o.farmer_id
            ORDER BY o.created_at DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll();
    }
}
