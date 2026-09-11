<?php
// src/Features/Auth/AuthController.php

require_once dirname(__DIR__, 2) . '/Core/bootstrap.php';

class AuthController
{
    public static function login(
        string $email,
        string $password
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare(
            "SELECT id, name, email, password, role, is_verified
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if (!$user) {
            flash('error', 'Invalid email or password.');
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            flash('error', 'Invalid email or password.');
            return false;
        }

        if (
            $user['role'] === 'farmer' &&
            (int)$user['is_verified'] !== 1
        ) {
            flash(
                'error',
                'Your farmer account has not been verified by the administrator.'
            );

            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'is_verified' => (int)$user['is_verified']
        ];

        return true;
    }

    public static function register(
        string $name,
        string $email,
        string $password,
        string $role,
        string $phone = '',
        string $location = ''
    ): bool {
        $pdo = DB::pdo();

        $allowedRoles = [
            'farmer',
            'buyer'
        ];

        if (!in_array($role, $allowedRoles, true)) {
            flash('error', 'Invalid account type.');
            return false;
        }

        if (strlen($password) < 6) {
            flash(
                'error',
                'Password must contain at least 6 characters.'
            );

            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            flash(
                'error',
                'An account with this email already exists.'
            );

            return false;
        }

        /*
         * Farmers require administrator verification.
         * Buyers are automatically verified.
         */
        $verified = $role === 'buyer' ? 1 : 0;

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare(
            "INSERT INTO users
            (
                name,
                email,
                password,
                role,
                phone,
                location,
                is_verified
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $name,
            $email,
            $hashedPassword,
            $role,
            $phone !== '' ? $phone : null,
            $location !== '' ? $location : null,
            $verified
        ]);

        if ($role === 'farmer') {
            flash(
                'success',
                'Registration successful. Your farmer account is waiting for administrator verification.'
            );
        } else {
            flash(
                'success',
                'Registration successful. You can now log in.'
            );
        }

        return true;
    }

    public static function logout(): void
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
}
