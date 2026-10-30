<?php
/**
 * AgriMALL database reset (Sprint 1, issue #4).
 *
 * Usage (PHP 8.1+ CLI; XAMPP: C:\xampp\php\php.exe tools\db-reset.php):
 *   php tools/db-reset.php --yes
 *
 * Drops and recreates `animal_marketplace`, applies database/schema.sql then
 * every database/migrations/*.sql in order, then runs database/seed.php.
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$config = require $base . '/config/config.php';

if (!is_array($config['db'] ?? null)) {
    fwrite(STDERR, "config/config.php missing or invalid. Copy config.example.php over it first." . PHP_EOL);
    exit(1);
}

if (!in_array('--yes', $argv ?? [], true)) {
    echo "This DROPS the entire `{$config['db']['name']}` database and re-seeds it." . PHP_EOL;
    echo "Re-run with --yes to confirm." . PHP_EOL;
    exit(1);
}

$db = $config['db'];
$dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']);
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $start = microtime(true);
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);

    echo "Dropping database `{$db['name']}`..." . PHP_EOL;
    $pdo->exec('DROP DATABASE IF EXISTS `' . $db['name'] . '`');
    echo "Creating database `{$db['name']}`..." . PHP_EOL;
    $pdo->exec('CREATE DATABASE `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $db['name'] . '`');

    // Each file has multiple statements; PDO::MYSQL_ATTR_MULTI_STATEMENTS is deprecated on PHP 8.5+.
    $multiAttr = defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
        ? \Pdo\Mysql::ATTR_MULTI_STATEMENTS
        : PDO::MYSQL_ATTR_MULTI_STATEMENTS;
    $pdo->setAttribute($multiAttr, true);

    $sqlFiles = [$base . '/database/schema.sql'];
    $migrations = glob($base . '/database/migrations/*.sql') ?: [];
    sort($migrations);
    foreach ($migrations as $file) {
        $sqlFiles[] = $file;
    }

    foreach ($sqlFiles as $file) {
        echo 'Applying ' . basename($file) . '...' . PHP_EOL;
        $pdo->exec(file_get_contents($file));
    }

    echo 'Seeding demo accounts + listings...' . PHP_EOL;
    require $base . '/database/seed.php';

    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $listings = (int) $pdo->query('SELECT COUNT(*) FROM produce_listings')->fetchColumn();
    printf("Reset complete in %.1f s (%d users, %d listings)." . PHP_EOL, microtime(true) - $start, $users, $listings);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'db-reset failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}