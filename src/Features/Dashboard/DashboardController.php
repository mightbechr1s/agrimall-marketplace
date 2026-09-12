<?php
// src/Features/Dashboard/DashboardController.php

require_once dirname(__DIR__, 2) . '/Core/bootstrap.php';

class DashboardController
{
    public static function getData(int $userId, string $role): array
    {
        $pdo = DB::pdo();

        if ($role === 'admin') {
            return self::adminDashboard($pdo);
        }

        if ($role === 'farmer') {
            return self::farmerDashboard(
                $pdo,
                $userId
            );
        }

        return self::buyerDashboard(
            $pdo,
            $userId
        );
    }

    private static function adminDashboard(
        PDO $pdo
    ): array {
        $users = $pdo->query("
            SELECT COUNT(*) AS total
            FROM users
        ")->fetch()['total'];

        $farmers = $pdo->query("
            SELECT COUNT(*) AS total
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE r.slug = 'farmer'
        ")->fetch()['total'];

        $pendingFarmers = $pdo->query("
            SELECT COUNT(*) AS total
            FROM farms
            WHERE verification_status = 'pending'
        ")->fetch()['total'];

        $verifiedFarmers = $pdo->query("
            SELECT COUNT(*) AS total
            FROM farms
            WHERE verification_status = 'verified'
        ")->fetch()['total'];

        $buyers = $pdo->query("
            SELECT COUNT(*) AS total
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE r.slug = 'buyer'
        ")->fetch()['total'];

        $listings = $pdo->query("
            SELECT COUNT(*) AS total
            FROM listings
        ")->fetch()['total'];

        $activeListings = $pdo->query("
            SELECT COUNT(*) AS total
            FROM listings
            WHERE status = 'active'
        ")->fetch()['total'];

        $orders = $pdo->query("
            SELECT COUNT(*) AS total
            FROM orders
        ")->fetch()['total'];

        $pendingOrders = $pdo->query("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE status = 'pending'
        ")->fetch()['total'];

        $completedOrders = $pdo->query("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE status = 'completed'
        ")->fetch()['total'];

        return [
            'users' => (int)$users,
            'farmers' => (int)$farmers,
            'pending_farmers' => (int)$pendingFarmers,
            'verified_farmers' => (int)$verifiedFarmers,
            'buyers' => (int)$buyers,
            'listings' => (int)$listings,
            'active_listings' => (int)$activeListings,
            'orders' => (int)$orders,
            'pending_orders' => (int)$pendingOrders,
            'completed_orders' => (int)$completedOrders
        ];
    }

    private static function farmerDashboard(
        PDO $pdo,
        int $farmerId
    ): array {
        $stmt = $pdo->prepare("
            SELECT id
            FROM farms
            WHERE user_id = ?
            LIMIT 1
        ");

        $stmt->execute([$farmerId]);

        $farm = $stmt->fetch();

        if (!$farm) {
            return [
                'farm' => null,
                'listings' => 0,
                'active_listings' => 0,
                'orders' => 0,
                'pending_orders' => 0,
                'completed_orders' => 0,
                'recent_orders' => []
            ];
        }

        $farmId = (int)$farm['id'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM listings
            WHERE farm_id = ?
        ");

        $stmt->execute([$farmId]);

        $listings = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM listings
            WHERE farm_id = ?
              AND status = 'active'
        ");

        $stmt->execute([$farmId]);

        $activeListings = $stmt->fetch()['total'];

        $left = "
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            WHERE l.farm_id = ?
        ";

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            {$left}
        ");

        $stmt->execute([$farmId]);

        $orders = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            {$left}
              AND o.status = 'pending'
        ");

        $stmt->execute([$farmId]);

        $pendingOrders = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            {$left}
              AND o.status = 'completed'
        ");

        $stmt->execute([$farmId]);

        $completedOrders = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.quantity,
                o.unit_price,
                IFNULL(o.delivery_fee, 0) AS delivery_fee,
                o.order_method,
                o.status,
                o.created_at,
                l.name AS listing_name,
                u.name AS buyer_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN users u
                ON u.id = o.buyer_id
            WHERE l.farm_id = ?
            ORDER BY o.created_at DESC
            LIMIT 5
        ");

        $stmt->execute([$farmId]);

        $recentOrders = $stmt->fetchAll();

        return [
            'farm' => $farm,
            'listings' => (int)$listings,
            'active_listings' => (int)$activeListings,
            'orders' => (int)$orders,
            'pending_orders' => (int)$pendingOrders,
            'completed_orders' => (int)$completedOrders,
            'recent_orders' => $recentOrders
        ];
    }

    private static function buyerDashboard(
        PDO $pdo,
        int $buyerId
    ): array {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE buyer_id = ?
        ");

        $stmt->execute([$buyerId]);

        $orders = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE buyer_id = ?
              AND status = 'pending'
        ");

        $stmt->execute([$buyerId]);

        $pendingOrders = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE buyer_id = ?
              AND status = 'completed'
        ");

        $stmt->execute([$buyerId]);

        $completedOrders = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE buyer_id = ?
              AND status = 'delivery_fee_review'
        ");

        $stmt->execute([$buyerId]);

        $deliveryReviews = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.quantity,
                o.unit_price,
                IFNULL(o.delivery_fee, 0) AS delivery_fee,
                o.order_method,
                o.status,
                o.created_at,
                l.name AS listing_name,
                f.farm_name AS farm_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            WHERE o.buyer_id = ?
            ORDER BY o.created_at DESC
            LIMIT 5
        ");

        $stmt->execute([$buyerId]);

        $recentOrders = $stmt->fetchAll();

        return [
            'orders' => (int)$orders,
            'pending_orders' => (int)$pendingOrders,
            'completed_orders' => (int)$completedOrders,
            'delivery_reviews' => (int)$deliveryReviews,
            'recent_orders' => $recentOrders
        ];
    }
}