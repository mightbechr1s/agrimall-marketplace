<?php
declare(strict_types=1);
/**
 * POST /api/preferences.php — persist UI preferences to the user's account.
 * Currently supports `theme` ('system' | 'light' | 'dark').
 * Requires an authenticated session and a valid CSRF token.
 */

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$userId = current_user_id();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
}

csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

$theme = $_POST['theme'] ?? '';
if (!in_array($theme, ['system', 'light', 'dark'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_theme']);
    exit;
}

try {
    DB::pdo()->prepare(
        'INSERT INTO user_preferences (user_id, theme, updated_at)
         VALUES (:id, :theme, NOW())
         ON DUPLICATE KEY UPDATE theme = VALUES(theme), updated_at = NOW()'
    )->execute(['id' => $userId, 'theme' => $theme]);

    echo json_encode(['ok' => true, 'theme' => $theme]);
} catch (Throwable $e) {
    error_log('Theme preference save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save_failed']);
}