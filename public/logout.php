<?php
require __DIR__ . '/../src/bootstrap.php';

// Revoke remember-me before session destroy. current_user_id() may be null
// (session expired) while remember cookie still resolves; revoke by both.
try {
    remember_revoke(current_user_id());
} catch (Throwable $e) {
    error_log('Remember-me revoke failed: ' . $e->getMessage());
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
redirect_to('index.php');