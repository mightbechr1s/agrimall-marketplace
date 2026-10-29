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

    // Opening stock ledger row; tools/stock_ledger_backfill.php adds the same row for older listings.
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

    // Seed one image row per listing: listings with no image fall back to this photo at render time.
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

function makeOrder(PDO $pdo, int $buyerId, int $farmerId, int $listingId, float $qty, float $price, float $fee, string $method, string $status, ?string $address, int $minsAgo = 360, ?string $reason = null): void
{
    // Walk the lifecycle by the status the order actually reached; pickup orders
    // skip out_for_delivery, but a seeded delivery order keeps its delivery chain.
    $deliveryMid = ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'];
    $pickupMid = ['confirmed', 'preparing', 'ready_for_pickup'];
    if ($status === 'out_for_delivery' || ($status === 'completed' && $method !== 'pickup')) {
        $mid = $deliveryMid;
    } else {
        $mid = $pickupMid;
    }
    if (in_array($status, $mid, true)) {
        $chain = array_slice($mid, 0, array_search($status, $mid, true) + 1);
    } else {
        $chain = [
            'pending'             => [],
            'delivery_fee_review' => ['delivery_fee_review'],
            'rejected'            => ['rejected'],
            'cancelled'           => ['confirmed', 'cancelled'],
            'completed'           => array_merge($mid, ['completed']),
        ][$status] ?? [];
    }
    $first = $method === 'farmer_delivery' ? 'delivery_fee_review' : 'pending';

    $created = date('Y-m-d H:i:s', time() - ($minsAgo * 60));
    $stepTs = [];
    $n = count($chain);
    foreach ($chain as $i => $step) {
        $stepTs[$step] = date('Y-m-d H:i:s', time() - (int) round($minsAgo * 60 * (1 - ($i + 1) / ($n + 1))));
    }
    $tsCols = [
        'confirmed'        => 'confirmed_at',
        'preparing'        => 'preparing_at',
        'ready_for_pickup' => 'ready_at',
        'out_for_delivery' => 'out_for_delivery_at',
        'completed'        => 'completed_at',
        'rejected'         => 'rejected_at',
        'cancelled'        => 'cancelled_at',
    ];

    $cols = ['order_number', 'buyer_id', 'farmer_id', 'listing_id', 'quantity', 'unit_price',
             'delivery_fee', 'delivery_method', 'delivery_address', 'status', 'created_at',
             'preferred_pickup_date', 'preferred_pickup_time'];
    $bind = [
        'order_number' => 'ORD-' . strtoupper(bin2hex(random_bytes(4))),
        'buyer_id' => $buyerId,
        'farmer_id' => $farmerId,
        'listing_id' => $listingId,
        'quantity' => $qty,
        'unit_price' => $price,
        'delivery_fee' => $fee,
        'delivery_method' => $method,
        'delivery_address' => $address,
        'status' => $status,
        'created_at' => $created,
        'preferred_pickup_date' => $method === 'pickup' ? date('Y-m-d', strtotime('+2 days')) : null,
        'preferred_pickup_time' => $method === 'pickup' ? '09:00:00' : null,
    ];
    if ($status === 'rejected') {
        $cols[] = 'rejection_reason';
        $bind['rejection_reason'] = $reason ?? 'Listing no longer available.';
    }
    if ($status === 'cancelled') {
        $cols[] = 'cancellation_reason';
        $cols[] = 'cancelled_by_role';
        $bind['cancellation_reason'] = $reason ?? 'Buyer cancelled the order.';
        $bind['cancelled_by_role'] = 'buyer';
    }
    foreach ($chain as $step) {
        if (!array_key_exists($step, $tsCols)) continue;   // delivery_fee_review has no dedicated column
        $cols[] = $tsCols[$step];
        $bind[$tsCols[$step]] = $stepTs[$step];
    }
    $sets = [];
    for ($i = 0; $i < count($cols); $i++) {
        $sets[] = ':' . ltrim($cols[$i], ':') ;
    }
    $stmt = $pdo->prepare('INSERT INTO orders (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $sets) . ')');
    $stmt->execute($bind);

    $orderId = (int) $pdo->lastInsertId();

    // Timeline: one history row per transition, buyer booked it, farmer walks the rest.
    $history = $pdo->prepare(
        'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, changed_by_role, reason)
         VALUES (:order_id, :from_status, :to_status, :changed_by, :changed_by_role, :reason)'
    );
    $reasons = [
        'pending'            => 'Order requested.',
        'delivery_fee_review' => 'Awaiting farmer delivery-fee quote.',
        'confirmed'          => 'Order confirmed by farmer.',
        'preparing'          => 'Farmer is preparing the order.',
        'ready_for_pickup'   => 'Ready for pickup.',
        'out_for_delivery'   => 'Out for delivery.',
        'completed'          => 'Order completed.',
        'rejected'           => 'Order rejected by farmer.',
        'cancelled'          => 'Order cancelled by buyer.',
    ];
    $prev = null;
    $actor = $buyerId;
    $role = 'buyer';
    $walk = $chain;
    if ($first !== 'pending' || !in_array($first, $chain, true)) {
        if ($chain && $chain[0] !== $first) {
            $history->execute(['order_id' => $orderId, 'from_status' => null, 'to_status' => $first,
                'changed_by' => $buyerId, 'changed_by_role' => 'buyer', 'reason' => $reasons[$first] ?? null]);
            $prev = $first;
        }
    }
    if (!$chain && $first === $status) {
        $history->execute(['order_id' => $orderId, 'from_status' => null, 'to_status' => $first,
            'changed_by' => $buyerId, 'changed_by_role' => 'buyer', 'reason' => $reasons[$first] ?? null]);
    }
    foreach ($walk as $step) {
        $isFarmerStep = in_array($step, ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'rejected'], true);
        $actor = $isFarmerStep ? $farmerId : $buyerId;
        $role = $isFarmerStep ? 'farmer' : 'buyer';
        $history->execute(['order_id' => $orderId, 'from_status' => $prev, 'to_status' => $step,
            'changed_by' => $actor, 'changed_by_role' => $role, 'reason' => $reasons[$step] ?? null]);
        $prev = $step;
    }

    // Completed orders must deduct stock (FR-ORD-010, BR-009); tools/stock_ledger_backfill.php repairs drift.
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
        'image' => 'assets/images/products/Sitaw_Long_Beans.jpg',
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