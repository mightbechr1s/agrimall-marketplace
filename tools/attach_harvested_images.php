<?php
// Attach the most accurate bundled product photo as each listing's primary image.
require __DIR__ . '/../src/bootstrap.php';

$dir = __DIR__ . '/../public/assets/images/products';
$pdo = DB::pdo();
$rows = $pdo->query("SELECT id, name FROM produce_listings WHERE name <> 'SCAN PROBE Delete Me'")->fetchAll();
$photoAliases = [
    'Alugbati (Malabar Spinach)' => 'Alugbati_Malabar_Spinach.jpg',
    'Ampalaya (Bitter Gourd)' => 'Ampalaya_Bitter_Gourd.jpg',
    'Banana' => 'Saba_Bananas.jpg',
    'Bawang (Native Garlic)' => 'Bawang_Garlic.jpg',
    'Cabbage' => 'Repolyo_Cabbage.jpg',
    'Calamansi' => 'Calamansi.jpg',
    'Corn' => 'Sweet_Corn.jpg',
    'Eggplant' => 'Organic_Eggplant.jpg',
    'Ginger' => 'Luya_Ginger.jpg',
    'Itlog (Chicken Eggs, Tray)' => 'chicken_eggs_tray.jpg',
    'Kamote (Sweet Potato)' => 'Sweet_Potato_Ube_Santana.jpg',
    'Kamoteng Kahoy (Cassava)' => 'Kamoteng_Kahoy_Cassava.jpg',
    'Kangkong (Water Spinach)' => 'Kangkong_Water_Spinach.jpg',
    'Lansones (Paete)' => 'lansones.jpg',
    'Mais (Sweet Corn)' => 'Sweet_Corn.jpg',
    'Malunggay' => 'Malunggay_Moringa.jpg',
    'Malunggay Leaves (Bundle)' => 'Malunggay_Moringa.jpg',
    'Mango' => 'mango.jpg',
    'Munggo (Mung Beans)' => 'Monggo_Mung_Beans.jpg',
    'Okra' => 'Okra.jpg',
    'Papaya' => 'Papaya.jpg',
    'Pechay' => 'Pechay_Bok_Choy.jpg',
    'Sack of Rice (50 kg)' => 'Sack_of_Rice_50kg.jpg',
    'Sitaw (String Beans)' => 'Sitaw_Long_Beans.jpg',
    'Squash' => 'Kalabasa_Squash.jpg',
    'Sweet Potato (Ube Santana)' => 'Sweet_Potato_Ube_Santana.jpg',
    'Talong (Long Purple Eggplant)' => 'Eggplant.jpg',
    'Tomato' => 'Organic_Tomatoes.jpg',
    'Upo (Bottle Gourd)' => 'Upo_Bottle_Gourd.jpg',
    "Siling Labuyo (Bird's Eye Chili)" => 'Siling_Labuyo_Bird_Chillies.jpg',
];

$get = $pdo->prepare('SELECT id FROM listing_images WHERE listing_id = :lid AND is_primary = 1 LIMIT 1');
$upd = $pdo->prepare('UPDATE listing_images SET image_path = :path WHERE id = :id');
$ins = $pdo->prepare('INSERT INTO listing_images (listing_id, image_path, is_primary, sort_order) VALUES (:lid, :path, 1, 0)');

$ok = 0; $missing = [];
foreach ($rows as $r) {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $r['name']), '_'));
    $file = $photoAliases[$r['name']] ?? 'harv_' . $slug . '.jpg';
    if (!is_file($dir . '/' . $file)) { $missing[] = $r['name']; continue; }
    $path = 'assets/images/products/' . $file;
    $get->execute(['lid' => $r['id']]);
    $imgId = $get->fetchColumn();
    if ($imgId) $upd->execute(['path' => $path, 'id' => $imgId]);
    else $ins->execute(['lid' => $r['id'], 'path' => $path]);
    $ok++;
}
echo "attached: $ok\n";
if ($missing) echo "missing: " . implode(', ', $missing) . "\n";
