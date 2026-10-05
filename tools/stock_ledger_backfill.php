<?php
/**
 * Reconcile the stock ledger for data created before stock_adjustments existed.
 *
 * Usage (PHP 8.1+ CLI; XAMPP: C:\xampp\php\php.exe tools\stock_ledger_backfill.php):
 *   php tools/stock_ledger_backfill.php            # report only, changes nothing
 *   php tools/stock_ledger_backfill.php --apply    # write the missing rows
 *
 * Two defects are corrected here, and both are historical, not ongoing:
 *
 *   1. Listings inserted before the ledger existed have no `initial` row, so
 *      their quantity has no recorded origin.
 *   2. Orders confirmed before FR-ORD-010 was implemented never deducted stock,
 *      so those listings are overstated by the confirmed quantity (BR-009).
 *
 * A confirmed-then-cancelled order nets to zero: it gets a `sale_deduction` and
 * a matching `return`, so the trail is complete and the quantity is unchanged.
 * An order that was never confirmed reserves nothing and is left alone (BR-008).
 *
 * Safe to run repeatedly. Every write is guarded by the absence of the row it
 * would create, so a second run reports no work and changes nothing.
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$config = require $base . '/config/config.php';

if (!is_array($config['db'] ?? null)) {
    fwrite(STDERR, "config/config.php missing or invalid." . PHP_EOL);
    exit(1);
}

$apply = in_array('--apply', $argv ?? [], true);
$db = $config['db'];
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// A confirmed order that later reached a restorable status had its stock
// returned (BR-036), so the pair of rows nets to zero.
const RESTORED_STATUSES = ['cancelled'];

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
} catch (PDOException $e) {
    fwrite(STDERR, 'Cannot connect to the database: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Target database: ' . $db['name'] . ($apply ? ' (APPLYING)' : ' (report only)') . PHP_EOL . PHP_EOL;

/** Trim trailing zeros so the report reads like the rest of the application. */
function fmt_qty(float $v): string
{
    return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0';
}

/**
 * Record the origin of stock that is already on hand. This writes a ledger row
 * only: the quantity in produce_listings is already correct, so adding the
 * opening amount again would double it. quantity_change equals the balance so
 * that SUM(quantity_change) per listing equals available_quantity.
 */
function ledger_open(PDO $pdo, int $listingId, float $qty, int $actorId, string $notes): void
{
    $pdo->prepare(
        'INSERT INTO stock_adjustments
         (listing_id, adjustment_type, quantity_change, quantity_before, quantity_after,
          reference_type, reference_id, notes, created_by)
         VALUES
         (:listing_id, \'initial\', :change, 0, :after, \'manual\', NULL, :notes, :created_by)'
    )->execute([
        'listing_id' => $listingId,
        'change'     => round($qty, 4),
        'after'      => round($qty, 4),
        'notes'      => $notes,
        'created_by' => $actorId,
    ]);
}

/**
 * Apply a real change to stock and record it, keeping the before/after chain
 * intact. Used for deductions and returns, where the quantity must move.
 */
function ledger(PDO $pdo, int $listingId, float $delta, string $type, ?int $orderId, int $actorId, string $notes): void
{
    $stmt = $pdo->prepare('SELECT available_quantity FROM produce_listings WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $listingId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Listing ' . $listingId . ' vanished during backfill.');
    }

    $before = (float) $row['available_quantity'];
    $after  = round($before + $delta, 4);

    if ($after < 0) {
        throw new RuntimeException('Listing ' . $listingId . ': only ' . $before . ' on hand, cannot deduct ' . abs($delta) . '.');
    }

    $pdo->prepare('UPDATE produce_listings SET available_quantity = :after WHERE id = :id')
        ->execute(['after' => $after, 'id' => $listingId]);

    $pdo->prepare(
        'INSERT INTO stock_adjustments
         (listing_id, adjustment_type, quantity_change, quantity_before, quantity_after,
          reference_type, reference_id, notes, created_by)
         VALUES
         (:listing_id, :type, :change, :before, :after, :ref_type, :ref_id, :notes, :created_by)'
    )->execute([
        'listing_id' => $listingId,
        'type'       => $type,
        'change'     => round($delta, 4),
        'before'     => $before,
        'after'      => $after,
        'ref_type'   => $orderId !== null ? 'order' : 'manual',
        'ref_id'     => $orderId,
        'notes'      => $notes,
        'created_by' => $actorId,
    ]);
}

// 1. Openings for listings that have no ledger at all.
$openings = $pdo->query(
    'SELECT pl.id, pl.farmer_id, pl.name, pl.available_quantity
       FROM produce_listings pl
      WHERE NOT EXISTS (SELECT 1 FROM stock_adjustments sa WHERE sa.listing_id = pl.id)
      ORDER BY pl.id'
)->fetchAll();

echo 'Listings needing an opening row: ' . count($openings) . PHP_EOL;

// 2. Confirmed orders that never moved stock.
$orders = $pdo->query(
    "SELECT o.id, o.order_number, o.listing_id, o.farmer_id, o.quantity, o.status,
            pl.available_quantity, pl.name
       FROM orders o
       JOIN produce_listings pl ON pl.id = o.listing_id
      WHERE o.confirmed_at IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM stock_adjustments sa
             WHERE sa.listing_id = o.listing_id
               AND sa.reference_type = 'order'
               AND sa.reference_id = o.id
               AND sa.adjustment_type = 'sale_deduction')
      ORDER BY o.id"
)->fetchAll();

echo 'Confirmed orders missing a deduction: ' . count($orders) . PHP_EOL . PHP_EOL;

$blockers = [];
foreach ($orders as $o) {
    $net = in_array($o['status'], RESTORED_STATUSES, true) ? 0.0 : -(float) $o['quantity'];
    if (round((float) $o['available_quantity'] + $net, 4) < 0) {
        $blockers[] = sprintf(
            '  order %s on listing %d (%s): deducting %s would exceed the %s on hand',
            $o['order_number'], $o['listing_id'], $o['name'], fmt_qty((float) $o['quantity']), fmt_qty((float) $o['available_quantity'])
        );
    }
}

if ($blockers) {
    echo 'REFUSING TO APPLY - these deductions would drive stock negative:' . PHP_EOL;
    echo implode(PHP_EOL, $blockers) . PHP_EOL;
    exit(1);
}

foreach ($openings as $l) {
    printf(
        "  opening  listing %-4d %-28s qty %s%s",
        $l['id'], mb_strimwidth($l['name'], 0, 28, ''), fmt_qty((float) $l['available_quantity']), PHP_EOL
    );
}
foreach ($orders as $o) {
    $restored = in_array($o['status'], RESTORED_STATUSES, true);
    printf(
        "  deduct   order %-6s listing %-4d %-28s qty %s%s%s",
        $o['order_number'], $o['listing_id'], mb_strimwidth($o['name'], 0, 28, ''),
        fmt_qty((float) $o['quantity']), $restored ? ' (net zero: later restored)' : '', PHP_EOL
    );
}

if (!$apply) {
    echo PHP_EOL . 'Report only. Re-run with --apply to write these rows.' . PHP_EOL;
    exit(0);
}

$pdo->beginTransaction();

foreach ($openings as $l) {
    ledger_open($pdo, (int) $l['id'], (float) $l['available_quantity'], (int) $l['farmer_id'],
        'Opening stock recorded by tools/stock_ledger_backfill.php for a listing created before the stock ledger existed.');
}

foreach ($orders as $o) {
    $qty = (float) $o['quantity'];
    ledger($pdo, (int) $o['listing_id'], -$qty, 'sale_deduction', (int) $o['id'], (int) $o['farmer_id'],
        'Order ' . $o['order_number'] . ' confirmed before stock deduction was implemented (FR-ORD-010, BR-009).');
    if (in_array($o['status'], RESTORED_STATUSES, true)) {
        ledger($pdo, (int) $o['listing_id'], $qty, 'return', (int) $o['id'], (int) $o['farmer_id'],
            'Stock restored for order ' . $o['order_number'] . ', which was confirmed and later cancelled (BR-036).');
    }
}

$pdo->commit();

echo PHP_EOL . 'Applied. Ledger rows now: '
    . $pdo->query('SELECT COUNT(*) FROM stock_adjustments')->fetchColumn() . PHP_EOL;