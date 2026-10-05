<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/DB.php';
DB::init($config['db']);

$pdo = DB::pdo();

function idBy(PDO $pdo, string $table, string $column, string $value): ?int
{
    $tables = ['produce_categories' => ['slug'], 'quantity_units' => ['symbol']];
    if (!isset($tables[$table]) || !in_array($column, $tables[$table], true)) {
        throw new InvalidArgumentException('Unsafe table/column pair.');
    }
    $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE `$column` = :v LIMIT 1");
    $stmt->execute(['v' => $value]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function userIdByEmail(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function makeUser(PDO $pdo, string $email): int
{
    $existing = userIdByEmail($pdo, $email);
    if ($existing !== null) {
        return $existing;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, role, status)
         VALUES (:email, :hash, :role, :status)'
    );
    $stmt->execute([
        'email' => $email,
        'hash' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'farmer',
        'status' => 'active',
    ]);
    return (int) $pdo->lastInsertId();
}

function makeBuyer(PDO $pdo, string $email, string $fullName, string $phone): int
{
    $existing = userIdByEmail($pdo, $email);
    if ($existing !== null) {
        return $existing;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, role, status)
         VALUES (:email, :hash, :role, :status)'
    );
    $stmt->execute([
        'email' => $email,
        'hash' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'buyer',
        'status' => 'active',
    ]);
    $userId = (int) $pdo->lastInsertId();

    $profile = $pdo->prepare(
        'INSERT INTO buyer_profiles (user_id, full_name, contact_phone)
         VALUES (:user_id, :full_name, :contact_phone)'
    );
    $profile->execute([
        'user_id' => $userId,
        'full_name' => $fullName,
        'contact_phone' => $phone,
    ]);
    return $userId;
}

function makeFarmerProfile(PDO $pdo, int $userId, array $farm): void
{
    $exists = $pdo->prepare('SELECT 1 FROM farmer_profiles WHERE user_id = :id LIMIT 1');
    $exists->execute(['id' => $userId]);
    if ($exists->fetch()) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO farmer_profiles
            (user_id, farm_name, farm_description, contact_person, contact_phone, contact_email,
             address, barangay, municipality_city, province, region,
             valid_id_type, valid_id_number, verification_status, verified_at)
         VALUES
            (:user_id, :farm_name, :farm_description, :contact_person, :contact_phone, :contact_email,
             :address, :barangay, :municipality, :province, :region,
             :valid_id_type, :valid_id_number, :verification_status, NOW())'
    );
    $stmt->execute([
        'user_id' => $userId,
        'farm_name' => $farm['farm_name'],
        'farm_description' => $farm['farm_description'],
        'contact_person' => $farm['contact_person'],
        'contact_phone' => $farm['contact_phone'],
        'contact_email' => $farm['contact_email'],
        'address' => $farm['address'],
        'barangay' => $farm['barangay'],
        'municipality' => $farm['municipality_city'],
        'province' => $farm['province'],
        'region' => $farm['region'],
        'valid_id_type' => 'national_id',
        'valid_id_number' => $farm['valid_id_number'],
        'verification_status' => 'approved',
    ]);
}

function makeListing(PDO $pdo, int $farmerId, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO produce_listings
            (farmer_id, category_id, unit_id, name, description, price_per_unit,
             available_quantity, minimum_order_qty, maximum_order_qty,
             harvest_date, expiry_date, is_organic, status, published_at)
         VALUES
            (:farmer_id, :category_id, :unit_id, :name, :description, :price_per_unit,
             :available_quantity, :minimum_order_qty, :maximum_order_qty,
             :harvest_date, :expiry_date, :is_organic, :status, NOW())'
    );
    $stmt->execute([
        'farmer_id' => $farmerId,
        'category_id' => $data['category_id'],
        'unit_id' => $data['unit_id'],
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'price_per_unit' => $data['price_per_unit'],
        'available_quantity' => $data['available_quantity'],
        'minimum_order_qty' => $data['minimum_order_qty'] ?? 1,
        'maximum_order_qty' => $data['maximum_order_qty'] ?? null,
        'harvest_date' => $data['harvest_date'] ?? null,
        'expiry_date' => $data['expiry_date'] ?? null,
        'is_organic' => $data['is_organic'] ?? 0,
        'status' => 'active',
    ]);

    $listingId = (int) $pdo->lastInsertId();

    // Open the stock ledger for this listing, so every quantity in the database
    // is traceable to a starting row. tools/stock_ledger_backfill.php adds the
    // same row for listings that predate the ledger.
    $pdo->prepare(
        'INSERT INTO stock_adjustments
         (listing_id, adjustment_type, quantity_change, quantity_before, quantity_after,
          reference_type, reference_id, notes, created_by)
         VALUES
         (:listing_id, \'initial\', :change, 0, :after, \'manual\', NULL, :notes, :created_by)'
    )->execute([
        'listing_id'  => $listingId,
        'change'      => $data['available_quantity'],
        'after'       => $data['available_quantity'],
        'notes'       => 'Opening stock recorded at seed time.',
        'created_by'  => $farmerId,
    ]);

    // Listings without an image fall back to this real photo at render time,
    // so seed one row per listing to keep the marketplace from showing blanks.
    $image = $pdo->prepare(
        'INSERT INTO listing_images (listing_id, image_path, is_primary, sort_order)
         VALUES (:listing_id, :image_path, 1, 0)'
    );
    $image->execute([
        'listing_id' => $listingId,
        'image_path' => $data['image'] ?? 'assets/images/products/market.jpg',
    ]);

    return $listingId;
}

function makeOrder(PDO $pdo, int $buyerId, int $farmerId, int $listingId, float $qty, float $price, float $fee, string $method, string $status, ?string $address): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO orders
            (order_number, buyer_id, farmer_id, listing_id, quantity, unit_price, delivery_fee,
             delivery_method, delivery_address, status, confirmed_at)
         VALUES
            (:number, :buyer_id, :farmer_id, :listing_id, :quantity, :unit_price, :delivery_fee,
             :method, :address, :status, :confirmed_at)'
    );
    $stmt->execute([
        'number' => 'ORD-' . strtoupper(bin2hex(random_bytes(4))),
        'buyer_id' => $buyerId,
        'farmer_id' => $farmerId,
        'listing_id' => $listingId,
        'quantity' => $qty,
        'unit_price' => $price,
        'delivery_fee' => $fee,
        'method' => $method,
        'address' => $address,
        'status' => $status,
        'confirmed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
    ]);

    $orderId = (int) $pdo->lastInsertId();

    // A confirmed order consumes stock (FR-ORD-010, BR-009). Seeded orders that
    // start out completed must move the ledger too, or a fresh database would
    // disagree with the live one that tools/stock_ledger_backfill.php repairs.
    if ($status === 'completed') {
        $before = (float) $pdo->query('SELECT available_quantity FROM produce_listings WHERE id = ' . $listingId)->fetchColumn();
        $after  = round($before - $qty, 4);
        $pdo->prepare('UPDATE produce_listings SET available_quantity = :after WHERE id = :id')
            ->execute(['after' => $after, 'id' => $listingId]);
        $pdo->prepare(
            'INSERT INTO stock_adjustments
             (listing_id, adjustment_type, quantity_change, quantity_before, quantity_after,
              reference_type, reference_id, notes, created_by)
             VALUES
             (:listing_id, \'sale_deduction\', :change, :before, :after, \'order\', :ref_id, :notes, :created_by)'
        )->execute([
            'listing_id' => $listingId,
            'change'     => -$qty,
            'before'     => $before,
            'after'      => $after,
            'ref_id'     => $orderId,
            'notes'      => 'Seeded completed order ' . $orderId . '.',
            'created_by' => $farmerId,
        ]);
    }
}

$pdo->beginTransaction();

try {
    $vegetables = idBy($pdo, 'produce_categories', 'slug', 'vegetables');
    $leafy = idBy($pdo, 'produce_categories', 'slug', 'leafy-greens');
    $fruits = idBy($pdo, 'produce_categories', 'slug', 'fruits');
    $rootCrops = idBy($pdo, 'produce_categories', 'slug', 'root-crops');
    $grains = idBy($pdo, 'produce_categories', 'slug', 'grains-legumes');

    $kg = idBy($pdo, 'quantity_units', 'symbol', 'kg');
    $pc = idBy($pdo, 'quantity_units', 'symbol', 'pc');
    $bundle = idBy($pdo, 'quantity_units', 'symbol', 'bundle');
    $sack = idBy($pdo, 'quantity_units', 'symbol', 'sack');

    $farmer1 = makeUser($pdo, 'delagracia@animal.local');
    makeFarmerProfile($pdo, $farmer1, [
        'farm_name' => 'Dela Gracia Organic Farm',
        'farm_description' => 'Small family farm in Lucban, Quezon growing traditional leafy vegetables without synthetic pesticides.',
        'contact_person' => 'Rosa Dela Gracia',
        'contact_phone' => '09171234567',
        'contact_email' => 'delagracia@animal.local',
        'address' => 'Barangay San Roque, Lucban, Quezon',
        'barangay' => 'San Roque',
        'municipality_city' => 'Lucban',
        'province' => 'Quezon',
        'region' => 'CALABARZON (Region IV-A)',
        'valid_id_number' => 'NAID-1001-2021',
    ]);

    $farmer2 = makeUser($pdo, 'encarnacion@animal.local');
    makeFarmerProfile($pdo, $farmer2, [
        'farm_name' => 'Encarnacion Grains',
        'farm_description' => 'Rice and vegetable farm in the Guimba, Nueva Ecija plain.',
        'contact_person' => 'Efren Encarnacion',
        'contact_phone' => '09189876543',
        'contact_email' => 'encarnacion@animal.local',
        'address' => 'Purok 3, San Isidro, Guimba, Nueva Ecija',
        'barangay' => 'San Isidro',
        'municipality_city' => 'Guimba',
        'province' => 'Nueva Ecija',
        'region' => 'Central Luzon (Region III)',
        'valid_id_number' => 'NAID-2002-2019',
    ]);

    $buyer1 = makeBuyer($pdo, 'mercado@animal.local', 'Marites Mercado', '09175551234');
    $buyer2 = makeBuyer($pdo, 'dizon@animal.local', 'Karlo Dizon', '09163334444');

    $l1 = makeListing($pdo, $farmer1, [
        'category_id' => $vegetables,
        'unit_id' => $bundle,
        'name' => 'Sitaw (String Beans)',
        'description' => 'Fresh-picked string beans, harvested the same morning. Best for ginisa and adobo.',
        'image' => 'assets/images/products/Sitaw_String_Beans.jpg',
        'price_per_unit' => 45.00,
        'available_quantity' => 120,
        'minimum_order_qty' => 1,
        'is_organic' => 1,
        'harvest_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+5 days')),
    ]);
    $l2 = makeListing($pdo, $farmer1, [
        'category_id' => $leafy,
        'unit_id' => $bundle,
        'name' => 'Kangkong (Water Spinach)',
        'description' => 'Crisp kangkong bundles, great for sinigang and adobong kangkong.',
        'image' => 'assets/images/products/Kangkong_Water_Spinach.jpg',
        'price_per_unit' => 25.00,
        'available_quantity' => 200,
        'minimum_order_qty' => 1,
        'is_organic' => 1,
        'harvest_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+3 days')),
    ]);
    $l3 = makeListing($pdo, $farmer1, [
        'category_id' => $fruits,
        'unit_id' => $kg,
        'name' => 'Calamansi',
        'description' => 'Juicy calamansi grown in the Lucban highlands.',
        'image' => 'assets/images/products/Calamansi.jpg',
        'price_per_unit' => 80.00,
        'available_quantity' => 60,
        'minimum_order_qty' => 0.5,
        'is_organic' => 1,
        'harvest_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+14 days')),
    ]);
    $l4 = makeListing($pdo, $farmer2, [
        'category_id' => $grains,
        'unit_id' => $sack,
        'name' => 'Sack of Rice (50 kg)',
        'description' => 'Dinorado regular milled rice, one sack per order, delivered from the mill.',
        'image' => 'assets/images/products/Sack_of_Rice_50kg.jpg',
        'price_per_unit' => 2450.00,
        'available_quantity' => 20,
        'minimum_order_qty' => 1,
        'harvest_date' => date('Y-m-d', strtotime('-7 days')),
    ]);
    $l5 = makeListing($pdo, $farmer2, [
        'category_id' => $vegetables,
        'unit_id' => $kg,
        'name' => 'Eggplant',
        'description' => 'Long purple eggplant, uniform size, good for tortang talong.',
        'image' => 'assets/images/products/Eggplant.jpg',
        'price_per_unit' => 60.00,
        'available_quantity' => 150,
        'minimum_order_qty' => 1,
        'harvest_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+7 days')),
    ]);
    $l6 = makeListing($pdo, $farmer2, [
        'category_id' => $rootCrops,
        'unit_id' => $kg,
        'name' => 'Sweet Potato (Ube Santana)',
        'description' => 'Purple-fleshed sweet potato from Nueva Ecija, ideal for roasting and halaya.',
        'image' => 'assets/images/products/Sweet_Potato_Ube_Santana.jpg',
        'price_per_unit' => 40.00,
        'available_quantity' => 300,
        'minimum_order_qty' => 1,
        'expiry_date' => date('Y-m-d', strtotime('+30 days')),
    ]);

    makeOrder($pdo, $buyer1, $farmer1, $l1, 3, 45.00, 0.00, 'pickup', 'completed', null);
    makeOrder($pdo, $buyer2, $farmer2, $l4, 2, 2450.00, 0.00, 'farmer_delivery', 'pending', 'Purok 5, Camiling, Tarlac');
    makeOrder($pdo, $buyer1, $farmer2, $l5, 5, 60.00, 20.00, 'farmer_delivery', 'confirmed', '123 Mabini St, San Fernando, Pampanga');

    $pdo->commit();

    echo "Seed complete." . PHP_EOL;
    echo "  Farmers: delagracia@animal.local, encarnacion@animal.local (password123)" . PHP_EOL;
    echo "  Buyers:  mercado@animal.local, dizon@animal.local (password123)" . PHP_EOL;
    echo "  6 listings, 3 orders inserted." . PHP_EOL;
} catch (Throwable $ex) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $ex->getMessage() . PHP_EOL);
    exit(1);
}