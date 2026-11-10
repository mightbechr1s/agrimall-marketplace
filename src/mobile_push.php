<?php
/**
 * Expo push notifications for the mobile app.
 *
 * Unlike the web push (VAPID, push.php), Expo devices are addressed by an
 * ExponentPushToken and delivered through Expo's push service. Delivery is
 * best-effort: a failure is logged and never rolls back the action that
 * produced the notification.
 */

function mobile_push_send(PDO $pdo, int $userId, string $title, ?string $body = null, ?string $link = null): void
{
    if ($userId <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, token FROM mobile_push_tokens WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('mobile push lookup failed: ' . $e->getMessage());
        return;
    }
    if (!$rows) {
        return;
    }

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = [
            'to'    => $row['token'],
            'title' => mb_substr($title, 0, 120),
            'body'  => mb_substr((string) $body, 0, 240),
            'sound' => 'default',
            'data'  => ['link' => $link],
        ];
    }

    $payload = json_encode(count($messages) === 1 ? $messages[0] : $messages);
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents('https://exp.host/--/api/v2/push/send', false, $context);
    if ($response === false) {
        error_log('mobile push send failed: no response');
        return;
    }

    $data = json_decode($response, true);
    $tickets = $data['data'] ?? [];
    if (isset($tickets['status'])) {
        $tickets = [$tickets];
    }
    if (!is_array($tickets)) {
        return;
    }

    // Expo answers DeviceNotRegistered for tokens of uninstalled apps; drop them.
    foreach ($tickets as $index => $ticket) {
        $error = $ticket['details']['error'] ?? '';
        if ($error === 'DeviceNotRegistered' && isset($rows[$index])) {
            $pdo->prepare('DELETE FROM mobile_push_tokens WHERE id = :id')
                ->execute(['id' => $rows[$index]['id']]);
        }
    }
}
