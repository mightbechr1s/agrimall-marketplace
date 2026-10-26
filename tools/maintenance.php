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

echo "pruned: rate_limits={$c1}, password_resets={$c2}, read notifications={$c3}, over caps={$c4}\n";
