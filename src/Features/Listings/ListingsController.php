<?php
// src/Features/Listings/ListingController.php

require_once dirname(__DIR__, 2) . '/Core/bootstrap.php';

class ListingController
{
    public static function getActiveListings(array $filters = []): array
    {
        $pdo = DB::pdo();

        $sql = "
            SELECT
                l.*,
                c.name AS category_name,
                u.name AS farmer_name
            FROM listings l
            INNER JOIN categories c
                ON c.id = l.category_id
            INNER JOIN users u
                ON u.id = l.farmer_id
            WHERE l.status = 'active'
              AND u.is_verified = 1
        ";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    l.name LIKE ?
                    OR l.description LIKE ?
                    OR u.name LIKE ?
                )
            ";

            $search = '%' . $filters['search'] . '%';

            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND l.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['location'])) {
            $sql .= " AND l.location LIKE ?";
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
            $sql .= " AND l.availability_date = ?";
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
                u.name AS farmer_name,
                u.email AS farmer_email,
                u.phone AS farmer_phone
            FROM listings l
            INNER JOIN categories c
                ON c.id = l.category_id
            INNER JOIN users u
                ON u.id = l.farmer_id
            WHERE l.id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $listing = $stmt->fetch();

        return $listing ?: null;
    }


    public static function getFarmerListings(int $farmerId): array
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT
                l.*,
                c.name AS category_name
            FROM listings l
            INNER JOIN categories c
                ON c.id = l.category_id
            WHERE l.farmer_id = ?
            ORDER BY l.created_at DESC
        ");

        $stmt->execute([$farmerId]);

        return $stmt->fetchAll();
    }


    public static function create(
        int $farmerId,
        array $data
    ): bool {
        $pdo = DB::pdo();

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $categoryId = (int)($data['category_id'] ?? 0);
        $quantity = (float)($data['quantity'] ?? 0);
        $unit = trim($data['unit'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $location = trim($data['location'] ?? '');
        $availabilityDate = $data['availability_date'] ?? null;
        $image = $data['image'] ?? null;

        if ($name === '') {
            flash('error', 'Product name is required.');
            return false;
        }

        if ($categoryId <= 0) {
            flash('error', 'Please select a category.');
            return false;
        }

        if ($quantity <= 0) {
            flash('error', 'Quantity must be greater than zero.');
            return false;
        }

        if ($unit === '') {
            flash('error', 'Unit is required.');
            return false;
        }

        if ($price < 0) {
            flash('error', 'Price cannot be negative.');
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO listings
            (
                farmer_id,
                category_id,
                name,
                description,
                quantity,
                unit,
                price,
                location,
                availability_date,
                image,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        $stmt->execute([
            $farmerId,
            $categoryId,
            $name,
            $description !== '' ? $description : null,
            $quantity,
            $unit,
            $price,
            $location !== '' ? $location : null,
            $availabilityDate ?: null,
            $image
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
        $pdo = DB::pdo();

        $check = $pdo->prepare("
            SELECT id
            FROM listings
            WHERE id = ?
              AND farmer_id = ?
            LIMIT 1
        ");

        $check->execute([
            $listingId,
            $farmerId
        ]);

        if (!$check->fetch()) {
            flash(
                'error',
                'You do not have permission to edit this listing.'
            );

            return false;
        }

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $categoryId = (int)($data['category_id'] ?? 0);
        $quantity = (float)($data['quantity'] ?? 0);
        $unit = trim($data['unit'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $location = trim($data['location'] ?? '');
        $availabilityDate = $data['availability_date'] ?? null;
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

        if ($quantity < 0) {
            flash('error', 'Quantity cannot be negative.');
            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE listings
            SET
                category_id = ?,
                name = ?,
                description = ?,
                quantity = ?,
                unit = ?,
                price = ?,
                location = ?,
                availability_date = ?,
                status = ?
            WHERE id = ?
              AND farmer_id = ?
        ");

        $stmt->execute([
            $categoryId,
            $name,
            $description !== '' ? $description : null,
            $quantity,
            $unit,
            $price,
            $location !== '' ? $location : null,
            $availabilityDate ?: null,
            $status,
            $listingId,
            $farmerId
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
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            DELETE FROM listings
            WHERE id = ?
              AND farmer_id = ?
        ");

        $stmt->execute([
            $listingId,
            $farmerId
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
