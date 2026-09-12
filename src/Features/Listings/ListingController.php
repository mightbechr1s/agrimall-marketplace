<?php
// src/Features/Listings/ListingController.php

require_once dirname(__DIR__, 2) . '/Core/bootstrap.php';

class ListingController
{
    /**
     * The farm owned by a user, if any.
     */
    private static function farmByOwner(int $userId): ?array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT *
            FROM farms
            WHERE user_id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $farm = $stmt->fetch();

        return $farm ?: null;
    }

    public static function getActiveListings(array $filters = []): array
    {
        $pdo = DB::pdo();

        $sql = "
            SELECT
                l.*,
                c.name AS category_name,
                f.farm_name AS farm_name,
                u.name AS farmer_name,
                f.location AS location
            FROM listings l
            INNER JOIN categories c
                ON c.id = l.category_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            INNER JOIN users u
                ON u.id = f.user_id
            WHERE l.status = 'active'
              AND f.verification_status = 'verified'
        ";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    l.name LIKE ?
                    OR l.description LIKE ?
                )
            ";

            $search = '%' . $filters['search'] . '%';

            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND l.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['location'])) {
            $sql .= " AND f.location LIKE ?";
            $params[] = '%' . $filters['location'] . '%';
        }

        if (!empty($filters['unit'])) {
            $sql .= " AND l.unit = ?";
            $params[] = $filters['unit'];
        }

        if (
            isset($filters['min_price']) &&
            $filters['min_price'] !== ''
        ) {
            $sql .= " AND l.price >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (
            isset($filters['max_price']) &&
            $filters['max_price'] !== ''
        ) {
            $sql .= " AND l.price <= ?";
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['date'])) {
            $sql .= "
                AND (
                    l.available_from IS NULL
                    OR l.available_from <= ?
                )
                AND (
                    l.available_until IS NULL
                    OR l.available_until >= ?
                )
            ";

            $params[] = $filters['date'];
            $params[] = $filters['date'];
        }

        $sql .= " ORDER BY l.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT
                l.*,
                c.name AS category_name,
                f.farm_name AS farm_name,
                f.location AS location,
                u.name AS farmer_name,
                u.email AS farmer_email
            FROM listings l
            INNER JOIN categories c
                ON c.id = l.category_id
            INNER JOIN farms f
                ON f.id = l.farm_id
            INNER JOIN users u
                ON u.id = f.user_id
            WHERE l.id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $listing = $stmt->fetch();

        return $listing ?: null;
    }

    public static function getFarmerId(int $listingId): ?int
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT u.id
            FROM listings l
            INNER JOIN farms f
                ON f.id = l.farm_id
            INNER JOIN users u
                ON u.id = f.user_id
            WHERE l.id = ?
            LIMIT 1
        ");

        $stmt->execute([$listingId]);

        $row = $stmt->fetch();

        return $row ? (int)$row['id'] : null;
    }

    public static function getFarmerListings(int $farmerId): array
    {
        $farm = self::farmByOwner($farmerId);

        if (!$farm) {
            return [];
        }

        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT
                l.*,
                c.name AS category_name
            FROM listings l
            INNER JOIN categories c
                ON c.id = l.category_id
            WHERE l.farm_id = ?
            ORDER BY l.created_at DESC
        ");

        $stmt->execute([$farm['id']]);

        return $stmt->fetchAll();
    }

    public static function create(
        int $farmerId,
        array $data
    ): bool {
        $farm = self::farmByOwner($farmerId);

        if (!$farm) {
            flash(
                'error',
                'You need a verified farm before you can list produce.'
            );

            return false;
        }

        $pdo = DB::pdo();

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $categoryId = (int)($data['category_id'] ?? 0);
        $quantityAvailable = (float)($data['quantity_available'] ?? 0);
        $unit = trim($data['unit'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $availableFrom = $data['available_from'] ?? null;
        $availableUntil = $data['available_until'] ?? null;
        $imagePath = $data['image_path'] ?? null;

        $allowedUnits = [
            'kg',
            'piece',
            'bundle',
            'sack',
            'box'
        ];

        if ($name === '') {
            flash('error', 'Product name is required.');
            return false;
        }

        if ($categoryId <= 0) {
            flash('error', 'Please select a category.');
            return false;
        }

        if ($quantityAvailable <= 0) {
            flash('error', 'Quantity must be greater than zero.');
            return false;
        }

        if (!in_array($unit, $allowedUnits, true)) {
            flash('error', 'Please select a valid unit.');
            return false;
        }

        if ($price < 0) {
            flash('error', 'Price cannot be negative.');
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO listings
            (
                farm_id,
                category_id,
                name,
                description,
                quantity_available,
                unit,
                price,
                available_from,
                available_until,
                image_path,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        $stmt->execute([
            $farm['id'],
            $categoryId,
            $name,
            $description !== '' ? $description : null,
            $quantityAvailable,
            $unit,
            $price,
            $availableFrom ?: null,
            $availableUntil ?: null,
            $imagePath
        ]);

        flash(
            'success',
            'Produce listing created successfully.'
        );

        return true;
    }

    public static function update(
        int $listingId,
        int $farmerId,
        array $data
    ): bool {
        $farm = self::farmByOwner($farmerId);

        if (!$farm) {
            flash(
                'error',
                'You do not have permission to edit this listing.'
            );

            return false;
        }

        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT id
            FROM listings
            WHERE id = ?
              AND farm_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $listingId,
            $farm['id']
        ]);

        if (!$stmt->fetch()) {
            flash(
                'error',
                'You do not have permission to edit this listing.'
            );

            return false;
        }

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $categoryId = (int)($data['category_id'] ?? 0);
        $quantityAvailable = (float)($data['quantity_available'] ?? 0);
        $unit = trim($data['unit'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $availableFrom = $data['available_from'] ?? null;
        $availableUntil = $data['available_until'] ?? null;
        $status = $data['status'] ?? 'active';

        $allowedStatuses = [
            'active',
            'sold_out',
            'inactive'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'active';
        }

        if ($name === '') {
            flash('error', 'Product name is required.');
            return false;
        }

        if ($categoryId <= 0) {
            flash('error', 'Please select a category.');
            return false;
        }

        if ($quantityAvailable < 0) {
            flash('error', 'Quantity cannot be negative.');
            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE listings
            SET
                category_id = ?,
                name = ?,
                description = ?,
                quantity_available = ?,
                unit = ?,
                price = ?,
                available_from = ?,
                available_until = ?,
                status = ?
            WHERE id = ?
              AND farm_id = ?
        ");

        $stmt->execute([
            $categoryId,
            $name,
            $description !== '' ? $description : null,
            $quantityAvailable,
            $unit,
            $price,
            $availableFrom ?: null,
            $availableUntil ?: null,
            $status,
            $listingId,
            $farm['id']
        ]);

        flash(
            'success',
            'Listing updated successfully.'
        );

        return true;
    }

    public static function delete(
        int $listingId,
        int $farmerId
    ): bool {
        $farm = self::farmByOwner($farmerId);

        if (!$farm) {
            flash(
                'error',
                'Listing could not be deleted.'
            );

            return false;
        }

        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            DELETE FROM listings
            WHERE id = ?
              AND farm_id = ?
        ");

        $stmt->execute([
            $listingId,
            $farm['id']
        ]);

        if ($stmt->rowCount() === 0) {
            flash(
                'error',
                'Listing could not be deleted.'
            );

            return false;
        }

        flash(
            'success',
            'Listing deleted successfully.'
        );

        return true;
    }

    public static function getCategories(): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->query("
            SELECT id, name
            FROM categories
            ORDER BY name ASC
        ");

        return $stmt->fetchAll();
    }
}