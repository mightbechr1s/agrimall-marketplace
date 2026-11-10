<?php
require __DIR__ . '/../src/bootstrap.php';

$pdo = DB::pdo();
$rows = $pdo->query("SELECT user_id, barangay, municipality_city, province FROM farmer_profiles WHERE latitude IS NULL OR longitude IS NULL")->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
foreach ($rows as $r) {
    $q = sprintf('%s, %s, %s, Philippines', $r['barangay'], $r['municipality_city'], $r['province']);
    $coords = geocode_address($q);
    if ($coords) {
        $pdo->prepare('UPDATE farmer_profiles SET latitude = :lat, longitude = :lng WHERE user_id = :uid')
            ->execute(['lat' => $coords[0], 'lng' => $coords[1], 'uid' => $r['user_id']]);
        $updated++;
        echo "geocoded user {$r['user_id']}: {$coords[0]}, {$coords[1]}\n";
    } else {
        echo "FAILED user {$r['user_id']}: $q\n";
    }
    // Nominatim rate limit: 1 req/sec
    usleep(1100000);
}
echo "Done. Updated $updated of " . count($rows) . " farmers.\n";
PY