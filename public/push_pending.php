<?php
require __DIR__ . '/../src/bootstrap.php';

// Called by the service worker after a push tickle. Returns the newest
// notification that has not been pushed yet and claims it, so a second tickle
// does not show the same text twice.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!current_user_id()) {
    http_response_code(401);
    echo json_encode(['error' => 'login_required']);
    exit;
}

try {
    $pdo = DB::pdo();
    $stmt = $pdo->prepare(
        'SELECT id, title, body, link
           FROM notifications
          WHERE user_id = :user AND push_sent_at IS NULL
          ORDER BY id DESC
          LIMIT 1'
    );
    $stmt->execute(['user' => current_user_id()]);
    $row = $stmt->fetch();

    if (!$row) {
        echo '{}';
        exit;
    }

    $pdo->prepare('UPDATE notifications SET push_sent_at = NOW() WHERE id = :id')
        ->execute(['id' => (int) $row['id']]);

    $link = (string) ($row['link'] ?? '');
    echo json_encode([
        'title' => (string) $row['title'],
        'body'  => (string) ($row['body'] ?? ''),
        'url'   => $link !== '' ? '/' . ltrim($link, '/') : '/notifications.php',
    ]);
} catch (Throwable $e) {
    app_log_exception($e, 'push_pending');
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
