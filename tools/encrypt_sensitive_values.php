<?php
/**
 * Encrypt plaintext `farmer_profiles.valid_id_number` rows that predate
 * security.encryption_key. Dry run by default.
 *
 *   php tools/encrypt_sensitive_values.php          # report only
 *   php tools/encrypt_sensitive_values.php --apply  # write ciphertext
 *
 * Anonymized rows ("ANON-...") are skipped: they are placeholders, not PII.
 */
require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$apply = in_array('--apply', $argv ?? [], true);
if (!sensitive_can_encrypt()) {
    fwrite(STDERR, "Refusing: security.encryption_key is not configured.\n");
    exit(1);
}

$pdo = DB::pdo();
$rows = $pdo->query(
    "SELECT user_id, valid_id_number FROM farmer_profiles
      WHERE valid_id_number <> ''
        AND valid_id_number NOT LIKE 'enc:v1:%'
        AND valid_id_number NOT LIKE 'ANON-%'"
)->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "Nothing to do: every ID number is already encrypted.\n";
    exit(0);
}

$update = $pdo->prepare('UPDATE farmer_profiles SET valid_id_number = :v WHERE user_id = :u');
foreach ($rows as $r) {
    echo ($apply ? 'encrypt ' : 'would encrypt ') . 'user ' . (int) $r['user_id'] . "\n";
    if ($apply) {
        $update->execute([
            'v' => sensitive_store((string) $r['valid_id_number']),
            'u' => (int) $r['user_id'],
        ]);
    }
}

echo $apply
    ? 'Encrypted ' . count($rows) . " row(s).\n"
    : 'Dry run: ' . count($rows) . " row(s). Re-run with --apply to write.\n";
