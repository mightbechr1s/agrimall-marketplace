<?php
require __DIR__ . '/../src/bootstrap.php';

// Revoke remember-me before the session is destroyed. current_user_id() can be
// null here (session already expired) while the remember cookie still resolves
// to a live row, so revoke by cookie as well as by user id.
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