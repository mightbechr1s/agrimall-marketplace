<?php
// Seed script: generates test data for exercising the UI.
// Run: php tools/seed_test_data.php
// All records use @seedtest.local emails and can be cleaned up with:
//   php tools/seed_test_data.php --clean

require __DIR__ . '/../src/bootstrap.php';

$clean = in_array('--clean', $argv ?? [], true);

$pdo = DB::pdo();

if ($clean) {
    $pdo->exec("DELETE FROM otp_codes WHERE recipient LIKE '%@seedtest.local'");
    $pdo->exec("DELETE FROM saved_searches WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local')");
    $pdo->exec("DELETE FROM farmer_reviews WHERE farmer_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local') OR buyer_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local')");
    $pdo->exec("DELETE FROM orders WHERE buyer_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local') OR farmer_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local')");
    $pdo->exec("DELETE FROM saved_listings WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local')");
    $pdo->exec("DELETE FROM listing_images WHERE listing_id IN (SELECT id FROM produce_listings WHERE farmer_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local'))");
    $pdo->exec("DELETE FROM produce_listings WHERE farmer_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local')");
    $pdo->exec("DELETE FROM farmer_profiles WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local')");
    $pdo->exec("DELETE FROM buyer_profiles WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local')");
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@seedtest.local'");
    echo "Cleaned up all seed test data.\n";
    exit(0);
}

// Check if already seeded
$existing = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@seedtest.local'")->fetchColumn();
if ($existing > 0) {
    echo "Seed data already exists ($existing users). Use --clean first to re-seed.\n";
    exit(0);
}

$names = ['Maria Santos', 'Juan Dela Cruz', 'Pedro Reyes', 'Ana Garcia', 'Jose Rizal', 'Rosa Mendoza', 'Carlos Tan', 'Luzviminda Cruz'];
$provinces = ['Quezon', 'Laguna', 'Batangas', 'Cavite', 'Rizal', 'Bulacan', 'Pampanga', 'Nueva Ecija'];
$categories = $pdo->query('SELECT id, name, slug FROM produce_categories WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC);
$units = $pdo->query('SELECT id, symbol FROM quantity_units WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC);

if (!$categories || !$units) {
    echo "No categories or units found. Run migrations first.\n";
    exit(1);
}

// Create farmers
$farmerIds = [];
foreach (array_slice($names, 0, 4) as $i => $name) {
    $email = 'farmer' . ($i + 1) . '@seedtest.local';
    $pdo->prepare("INSERT INTO users (email, password_hash, role, status) VALUES (:e, :h, 'farmer', 'active')")
        ->execute(['e' => $email, 'h' => password_hash('password123', PASSWORD_DEFAULT)]);
    $uid = (int) $pdo->lastInsertId();
    $farmerIds[] = $uid;
    $pdo->prepare("INSERT INTO farmer_profiles (user_id, farm_name, contact_person, contact_phone, contact_email, address, barangay, municipality_city, province, region, verification_status, verified_at) VALUES (:uid, :farm, :person, '09171234567', :email, :addr, 'San Roque', :mun, :prov, 'Region IV-A', 'approved', NOW())")
        ->execute([
            'uid' => $uid,
            'farm' => $name . "'s Farm",
            'person' => $name,
            'email' => $email,
            'addr' => 'Brgy. San Roque, ' . $provinces[$i],
            'mun' => $provinces[$i],
            'prov' => $provinces[$i],
        ]);
}

// Create buyers
$buyerIds = [];
foreach (array_slice($names, 4, 4) as $i => $name) {
    $email = 'buyer' . ($i + 1) . '@seedtest.local';
    $pdo->prepare("INSERT INTO users (email, password_hash, role, status) VALUES (:e, :h, 'buyer', 'active')")
        ->execute(['e' => $email, 'h' => password_hash('password123', PASSWORD_DEFAULT)]);
    $uid = (int) $pdo->lastInsertId();
    $buyerIds[] = $uid;
    $pdo->prepare("INSERT INTO buyer_profiles (user_id, full_name, contact_phone, delivery_address, barangay, municipality_city, province, region) VALUES (:uid, :name, '09181234567', '123 Main St', 'San Roque', :mun, :prov, 'Region IV-A')")
        ->execute(['uid' => $uid, 'name' => $name, 'mun' => $provinces[$i + 4], 'prov' => $provinces[$i + 4]]);
}

// Create listings
$produceNames = ['Rice', 'Monggo', 'Bawang', 'Kamatis', 'Sibuyas', 'Talong', 'Ampalaya', 'Sitaw', 'Pechay', 'Kangkong', 'Mais', 'Mani', 'Patatas', 'Gabi', 'Labanos'];
$listingIds = [];
foreach ($farmerIds as $fi => $farmerId) {
    $numListings = random_int(3, 6);
    for ($j = 0; $j < $numListings; $j++) {
        $cat = $categories[array_rand($categories)];
        $unit = $units[array_rand($units)];
        $price = round(random_int(20, 500) + (random_int(0, 99) / 100), 2);
        $qty = random_int(5, 200);
        $minQty = random_int(1, 5);
        $isOrganic = random_int(0, 1);
        $hasDiscount = random_int(0, 1);
        $discount = $hasDiscount ? random_int(5, 20) : 0;
        $harvestDate = date('Y-m-d', strtotime('-' . random_int(0, 7) . ' days'));
        $expiryDate = date('Y-m-d', strtotime('+' . random_int(3, 14) . ' days'));
        $expired = random_int(0, 5) === 0; // ~20% expired
        if ($expired) $expiryDate = date('Y-m-d', strtotime('-' . random_int(1, 5) . ' days'));
        $name = $produceNames[array_rand($produceNames)] . ' (Grade ' . chr(65 + random_int(0, 2)) . ')';
        $status = random_int(0, 10) < 9 ? 'active' : 'paused';
        $pdo->prepare(
            "INSERT INTO produce_listings (farmer_id, category_id, unit_id, name, description, price_per_unit, discount_percent, available_quantity, minimum_order_qty, harvest_date, expiry_date, is_organic, status, published_at)
             VALUES (:fid, :cid, :uid, :name, :desc, :price, :disc, :qty, :minq, :harvest, :expiry, :organic, :status, NOW())"
        )->execute([
            'fid' => $farmerId,
            'cid' => $cat['id'],
            'uid' => $unit['id'],
            'name' => $name,
            'desc' => 'Fresh ' . strtolower($cat['name']) . ' from ' . $provinces[$fi] . '. Harvested recently, top quality.',
            'price' => $price,
            'disc' => $discount,
            'qty' => $qty,
            'minq' => $minQty,
            'harvest' => $harvestDate,
            'expiry' => $expiryDate,
            'organic' => $isOrganic,
            'status' => $status,
        ]);
        $listingIds[] = (int) $pdo->lastInsertId();
    }
}

// Create orders
$statuses = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'completed', 'cancelled'];
foreach ($buyerIds as $buyerId) {
    $numOrders = random_int(2, 5);
    for ($j = 0; $j < $numOrders; $j++) {
        $listingId = $listingIds[array_rand($listingIds)];
        $listing = $pdo->prepare('SELECT * FROM v_active_listings WHERE id = :id');
        $listing->execute(['id' => $listingId]);
        $l = $listing->fetch(PDO::FETCH_ASSOC);
        if (!$l) continue;
        $qty = random_int(1, 5);
        $status = $statuses[array_rand($statuses)];
        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s', strtotime('-' . random_int(1, 30) . ' days')) : null;
        $pdo->prepare(
            "INSERT INTO orders (order_number, listing_id, buyer_id, farmer_id, quantity, unit_price, delivery_method, delivery_address, status, created_at, completed_at)
             VALUES (:ref, :lid, :bid, :fid, :qty, :price, :method, :addr, :status, :created, :completed)"
        )->execute([
            'ref' => order_ref(),
            'lid' => $listingId,
            'bid' => $buyerId,
            'fid' => $l['farmer_id'],
            'qty' => $qty,
            'price' => $l['price_per_unit'],
            'method' => random_int(0, 1) ? 'pickup' : 'farmer_delivery',
            'addr' => '123 Main St, San Roque',
            'status' => $status,
            'created' => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 60) . ' days')),
            'completed' => $completedAt,
        ]);
    }
}

// Create reviews for completed orders
$completedOrders = $pdo->query("SELECT o.id, o.farmer_id, o.buyer_id FROM orders o WHERE o.status = 'completed' AND o.farmer_id IN (SELECT id FROM users WHERE email LIKE '%@seedtest.local') LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
foreach ($completedOrders as $o) {
    if (random_int(0, 1) === 0) continue;
    $pdo->prepare(
        "INSERT INTO farmer_reviews (order_id, farmer_id, buyer_id, rating, comment, status) VALUES (:oid, :fid, :bid, :rating, :comment, 'visible')"
    )->execute([
        'oid' => $o['id'],
        'fid' => $o['farmer_id'],
        'bid' => $o['buyer_id'],
        'rating' => random_int(3, 5),
        'comment' => ['Great quality produce!', 'Fast delivery, will order again.', 'Fresh and affordable.', 'Good farmer, responsive.'][array_rand(['a','b','c','d'])],
    ]);
}

// Create saved searches for buyers
foreach ($buyerIds as $buyerId) {
    $pdo->prepare("INSERT INTO saved_searches (user_id, query, category, price_min, price_max) VALUES (:u, :q, :c, :mn, :mx)")
        ->execute([
            'u' => $buyerId,
            'q' => ['rice', 'tomato', 'garlic', ''][array_rand(['a','b','c','d'])],
            'c' => $categories[array_rand($categories)]['slug'],
            'mn' => random_int(0, 1) ? 10 : null,
            'mx' => random_int(0, 1) ? 200 : null,
        ]);
}

// Create saved listings
foreach ($buyerIds as $buyerId) {
    $numSaved = random_int(2, 5);
    for ($j = 0; $j < $numSaved; $j++) {
        $lid = $listingIds[array_rand($listingIds)];
        try {
            $pdo->prepare("INSERT INTO saved_listings (user_id, listing_id) VALUES (:u, :l)")
                ->execute(['u' => $buyerId, 'l' => $lid]);
        } catch (Throwable $e) { /* duplicate, skip */ }
    }
}

echo "Seed complete:\n";
echo "  Farmers: " . count($farmerIds) . "\n";
echo "  Buyers: " . count($buyerIds) . "\n";
echo "  Listings: " . count($listingIds) . "\n";
echo "  Orders: " . count($completedOrders) . " reviewed\n";
echo "  Password for all: password123\n";
echo "  Emails: farmer1-4@seedtest.local, buyer1-4@seedtest.local\n";
