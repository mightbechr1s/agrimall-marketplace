<?php
/**
 * ONE-TIME/PERIODIC maintenance sweep. The app had no pruning anywhere:
 * rate_limits, read notifications and expired password_resets grew forever.
 * Run before a demo or wire into Task Scheduler:
 *
 *   schtasks /create /tn "AniMALL maintenance" /sc daily /st 03:00 /tr ^
 *     "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\animal\tools\maintenance.php\""
 */
require __DIR__ . '/../src/bootstrap.php';
$pdo = DB::pdo();

// rate_limits: window-based rows stop mattering the moment the window expires.
$c1 = $pdo->exec('DELETE FROM rate_limits WHERE window_start < (UNIX_TIMESTAMP(NOW()) - 86400)');

// password_resets: expired tokens are only revoked per-user today; prune globally.
$c2 = $pdo->exec('DELETE FROM password_resets WHERE expires_at < NOW()');

// notifications: read ones past 30 days, plus keep only the newest 100 per user.
$c3 = $pdo->exec('DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < (NOW() - INTERVAL 30 DAY)');
$st = $pdo->prepare(
    'DELETE n FROM notifications n
      JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) rn
              FROM notifications) ranked ON ranked.id = n.id
     WHERE ranked.rn > 100'
);
$st->execute();
$c4 = $st->rowCount();

// Expiry nudge: tell buyers whose saved listings expire within 2 days.
try {
    $rows = $pdo->query(
        "SELECT DISTINCT sl.user_id, pl.id, pl.name, pl.expiry_date
           FROM saved_listings sl
           JOIN produce_listings pl ON pl.id = sl.listing_id
          WHERE pl.status = 'active'
            AND pl.expiry_date IS NOT NULL
            AND pl.expiry_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 2 DAY)"
    )->fetchAll();
    foreach ($rows as $r) {
        // Guard against duplicate nudges within 24h.
        $dup = $pdo->prepare(
            "SELECT 1 FROM notifications
              WHERE user_id = :u AND link = :l AND title = 'Saved item expiring soon'
                AND created_at > (NOW() - INTERVAL 1 DAY) LIMIT 1"
        );
        $dup->execute(['u' => $r['user_id'], 'l' => 'listing.php?id=' . $r['id']]);
        if ($dup->fetch()) continue;
        notify_user($pdo, (int) $r['user_id'], 'messages',
            'Saved item expiring soon',
            $r['name'] . ' expires on ' . $r['expiry_date'] . '. Order soon or it may be gone.',
            'listing.php?id=' . $r['id']);
    }
} catch (Throwable $e) {
    error_log('expiry nudge failed: ' . $e->getMessage());
}

echo "pruned: rate_limits={$c1}, password_resets={$c2}, read notifications={$c3}, over caps={$c4}\n";
