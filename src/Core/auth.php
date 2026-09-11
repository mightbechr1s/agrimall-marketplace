<?php
// src/Core/auth.php

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in first.');
        redirect('login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();

    $user = auth_user();

    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function is_admin(): bool
{
    return is_logged_in()
        && auth_user()['role'] === 'admin';
}

function is_farmer(): bool
{
    return is_logged_in()
        && auth_user()['role'] === 'farmer';
}

function is_buyer(): bool
{
    return is_logged_in()
        && auth_user()['role'] === 'buyer';
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'is_verified' => (int)$user['is_verified']
    ];
}

function logout_user(): void
{
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
}
