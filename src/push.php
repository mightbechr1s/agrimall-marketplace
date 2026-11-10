<?php
declare(strict_types=1);

/**
 * Web Push, payload-less variant.
 *
 * The push message carries no data; it only wakes the service worker, which then
 * fetches /push_pending.php with the user's session. That avoids implementing
 * aes128gcm payload encryption (no composer, no external library) while still
 * delivering the notification text and link. It also means a push can never
 * leak message content to the push service.
 */

function push_config(): array
{
    global $config;
    $push = $config['push'] ?? [];
    return is_array($push) ? $push : [];
}

function push_enabled(): bool
{
    $push = push_config();
    return !empty($push['enabled']) && !empty($push['public_key']) && !empty($push['private_key']);
}

function push_public_key(): string
{
    return (string) (push_config()['public_key'] ?? '');
}

function push_b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** openssl_sign returns DER; JWT ES256 needs the raw 32-byte R || S pair. */
function push_der_to_raw(string $der): ?string
{
    if ($der === '' || ord($der[0]) !== 0x30) {
        return null;
    }
    $pos = 1;
    $len = ord($der[$pos++]);
    if ($len & 0x80) {
        $bytes = $len & 0x7f;
        $len = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $len = ($len << 8) | ord($der[$pos++]);
        }
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        if (ord($der[$pos++]) !== 0x02) {
            return null;
        }
        $rlen = ord($der[$pos++]);
        $part = substr($der, $pos, $rlen);
        $pos += $rlen;
        $out .= str_pad(ltrim($part, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }
    return strlen($out) === 64 ? $out : null;
}

/** Build the VAPID Authorization header for one endpoint, or null on failure. */
function push_vapid_header(string $endpoint): ?string
{
    $push = push_config();
    $key = @openssl_pkey_get_private((string) ($push['private_key'] ?? ''));
    if (!$key) {
        return null;
    }
    $parts = parse_url($endpoint);
    if (!$parts || empty($parts['host'])) {
        return null;
    }
    $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');

    $header = push_b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = push_b64url(json_encode([
        'aud' => $origin,
        'exp' => time() + 12 * 3600,
        'sub' => (string) ($push['subject'] ?? 'mailto:admin@agrimall.ph'),
    ]));
    $signing = $header . '.' . $payload;
    if (!openssl_sign($signing, $der, $key, OPENSSL_ALGO_SHA256)) {
        return null;
    }
    $raw = push_der_to_raw($der);
    if ($raw === null) {
        return null;
    }
    return 'vapid t=' . $signing . '.' . push_b64url($raw) . ', k=' . push_public_key();
}

/**
 * Wake every subscribed browser for this user. Best-effort: a push service being
 * down must never break the order or message action that triggered it.
 */
function push_send_tickle(PDO $pdo, int $userId): void
{
    if (!push_enabled() || $userId <= 0) {
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT id, endpoint FROM push_subscriptions WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        $subs = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('push subscription lookup failed: ' . $e->getMessage());
        return;
    }

    foreach ($subs as $sub) {
        $auth = push_vapid_header((string) $sub['endpoint']);
        if ($auth === null) {
            continue;
        }
        $ch = curl_init((string) $sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $auth,
                'TTL: 3600',
                'Content-Length: 0',
            ],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // 404/410 means the browser subscription is gone for good.
        if ($code === 404 || $code === 410) {
            try {
                $pdo->prepare('DELETE FROM push_subscriptions WHERE id = :id')
                    ->execute(['id' => (int) $sub['id']]);
            } catch (Throwable $e) {
                error_log('push cleanup failed: ' . $e->getMessage());
            }
        }
    }
}
