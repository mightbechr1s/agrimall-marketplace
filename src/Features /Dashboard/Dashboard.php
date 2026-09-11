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
            FROM users
            WHERE role = 'farmer'
        ")->fetch()['total'];

        $pendingFarmers = $pdo->query("
            SELECT COUNT(*) AS total
            FROM users
            WHERE role = 'farmer'
              AND is_verified = 0
        ")->fetch()['total'];

        $buyers = $pdo->query("
            SELECT COUNT(*) AS total
            FROM users
            WHERE role = 'buyer'
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
            SELECT COUNT(*) AS total
            FROM listings
            WHERE farmer_id = ?
        ");

        $stmt->execute([$farmerId]);

        $listings = $stmt->fetch()['total'];


        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM listings
            WHERE farmer_id = ?
              AND status = 'active'
        ");

        $stmt->execute([$farmerId]);

        $activeListings = $stmt->fetch()['total'];


        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE farmer_id = ?
        ");

        $stmt->execute([$farmerId]);

        $orders = $stmt->fetch()['total'];


        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE farmer_id = ?
              AND status = 'pending'
        ");

        $stmt->execute([$farmerId]);

        $pendingOrders = $stmt->fetch()['total'];


        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE farmer_id = ?
              AND status = 'completed'
        ");

        $stmt->execute([$farmerId]);

        $completedOrders = $stmt->fetch()['total'];


        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.quantity,
                o.subtotal,
                o.status,
                o.created_at,
                l.name AS listing_name,
                u.name AS buyer_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN users u
                ON u.id = o.buyer_id
            WHERE o.farmer_id = ?
            ORDER BY o.created_at DESC
            LIMIT 5
        ");

        $stmt->execute([$farmerId]);

        $recentOrders = $stmt->fetchAll();


        return [
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
                o.subtotal,
                o.delivery_fee,
                o.fulfillment_type,
                o.status,
                o.created_at,
                l.name AS listing_name,
                u.name AS farmer_name
            FROM orders o
            INNER JOIN listings l
                ON l.id = o.listing_id
            INNER JOIN users u
                ON u.id = o.farmer_id
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
