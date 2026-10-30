<?php
/**
 * Nightly database backup with retention.
 *
 *   php tools/backup.php            # keep 7 dumps
 *   php tools/backup.php --keep=14
 *
 * Writes plain .sql dumps to backups/ (gitignored) and deletes older ones.
 * Schedule with Task Scheduler on Windows:
 *
 *   schtasks /create /tn "AgriMALL backup" /sc daily /st 02:00 /tr ^
 *     "\"C:\xampp\php\php.exe\" \"C:\path\to\agrimall\tools\backup.php\""
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "backup.php is CLI only.\n");
    exit(1);
}

require __DIR__ . '/../src/bootstrap.php';

$keep = 7;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--keep=(\d+)$/', $arg, $m)) {
        $keep = max(1, (int) $m[1]);
    }
}

$db = $config['db'];
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Cannot create backup directory: {$backupDir}\n");
    exit(1);
}

$candidates = [
    $db['dump_bin'] ?? '',
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    'mysqldump',
];
$dumpBin = null;
foreach ($candidates as $candidate) {
    if ($candidate === '') {
        continue;
    }
    if (str_contains($candidate, DIRECTORY_SEPARATOR) || str_contains($candidate, '/')) {
        if (is_file($candidate)) {
            $dumpBin = $candidate;
            break;
        }
    } else {
        $dumpBin = $candidate; // rely on PATH
        break;
    }
}
if ($dumpBin === null) {
    fwrite(STDERR, "mysqldump not found. Set db.dump_bin in config/config.php.\n");
    exit(1);
}

$file = $backupDir . DIRECTORY_SEPARATOR . 'agrimall-' . date('Ymd-His') . '.sql';
$command = escapeshellarg($dumpBin)
    . ' --host=' . escapeshellarg((string) $db['host'])
    . ' --port=' . escapeshellarg((string) $db['port'])
    . ' --user=' . escapeshellarg((string) $db['user']);
if ((string) $db['pass'] !== '') {
    $command .= ' --password=' . escapeshellarg((string) $db['pass']);
}
$command .= ' --single-transaction --routines --events --default-character-set=utf8mb4'
    . ' ' . escapeshellarg((string) $db['name'])
    . ' > ' . escapeshellarg($file);

exec($command, $output, $exitCode);
if ($exitCode !== 0 || !is_file($file) || filesize($file) < 1024) {
    @unlink($file);
    fwrite(STDERR, "Backup failed (exit {$exitCode}): " . implode(' ', $output) . "\n");
    exit(1);
}

// A dump without CREATE TABLE is a truncated or empty database, not a backup.
$head = (string) file_get_contents($file, false, null, 0, 65536);
if (!str_contains($head, 'CREATE TABLE')) {
    @unlink($file);
    fwrite(STDERR, "Backup looks invalid (no CREATE TABLE): {$file}\n");
    exit(1);
}

$dumps = glob($backupDir . DIRECTORY_SEPARATOR . 'agrimall-*.sql') ?: [];
usort($dumps, static fn(string $a, string $b): int => strcmp($b, $a));
foreach (array_slice($dumps, $keep) as $old) {
    @unlink($old);
}

printf(
    "backup ok: %s (%.1f KB), kept %d\n",
    basename($file),
    filesize($file) / 1024,
    min(count($dumps), $keep)
);
