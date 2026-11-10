<?php
declare(strict_types=1);

/**
 * Generate a VAPID key pair and print the config block.
 *
 * Usage: php tools/vapid-keys.php
 *
 * Run once per deployment. Keep the private key out of source control
 * (config/config.php is gitignored).
 */
// Windows builds of PHP ship without OPENSSL_CONF set, so the curve table is
// empty and key creation fails. Pass XAMPP's config explicitly instead.
$opensslConfig = null;
foreach ([
    dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
    dirname(PHP_BINARY) . '/extras/openssl/openssl.cnf',
    'C:/xampp/apache/conf/openssl.cnf',
] as $cnf) {
    if (is_file($cnf)) {
        $opensslConfig = $cnf;
        break;
    }
}

$key = openssl_pkey_new([
    'config' => $opensslConfig,
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name'       => 'prime256v1',
]);
if (!$key) {
    fwrite(STDERR, 'Could not create an EC key pair.' . PHP_EOL);
    exit(1);
}

$details = openssl_pkey_get_details($key);
$x = $details['ec']['x'] ?? '';
$y = $details['ec']['y'] ?? '';
if ($x === '' || $y === '') {
    fwrite(STDERR, 'Could not read the public point.' . PHP_EOL);
    exit(1);
}
if (!openssl_pkey_export($key, $pem, null, ['config' => $opensslConfig]) || !is_string($pem)) {
    fwrite(STDERR, 'Could not export the private key.' . PHP_EOL);
    exit(1);
}

$b64url = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
$public = $b64url("\x04" . str_pad($x, 32, "\x00", STR_PAD_LEFT) . str_pad($y, 32, "\x00", STR_PAD_LEFT));

echo "Add this block to config/config.php:" . PHP_EOL . PHP_EOL;
echo "    'push' => [" . PHP_EOL;
echo "        'enabled'     => true," . PHP_EOL;
echo "        'subject'     => 'mailto:admin@agrimall.ph'," . PHP_EOL;
echo "        'public_key'  => '" . $public . "'," . PHP_EOL;
$escaped = str_replace(["\r\n", "\n"], '\n', trim($pem));
echo '        \'private_key\' => "' . $escaped . '",' . PHP_EOL;
echo "    ]," . PHP_EOL;
echo PHP_EOL . "The private_key value is one double-quoted line with \\n escapes." . PHP_EOL;
