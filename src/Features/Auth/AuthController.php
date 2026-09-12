<?php
// src/Features/Auth/AuthController.php

require_once dirname(__DIR__, 2) . '/Core/bootstrap.php';

class AuthController
{
    /**
     * Map a role slug to a roles.id.
     */
    private static function roleId(string $slug): ?int
    {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT id
            FROM roles
            WHERE slug = ?
            LIMIT 1
        ");

        $stmt->execute([$slug]);

        $row = $stmt->fetch();

        return $row ? (int)$row['id'] : null;
    }

    /**
     * A farmer can log in only when their farm is verified.
     */
    public static function login(
        string $email,
        string $password
    ): bool {
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.password_hash, r.slug AS role
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE u.email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if (!$user) {
            flash('error', 'Invalid email or password.');
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            flash('error', 'Invalid email or password.');
            return false;
        }

        $verified = 0;

        if ($user['role'] === 'farmer') {
            $stmt = $pdo->prepare("
                SELECT verification_status
                FROM farms
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->execute([$user['id']]);

            $farm = $stmt->fetch();

            if ($farm && $farm['verification_status'] === 'verified') {
                $verified = 1;
            } else {
                flash(
                    'error',
                    'Your farm has not been verified by the administrator yet.'
                );

                return false;
            }
        } else {
            $verified = 1;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'          => (int)$user['id'],
            'name'        => $user['name'],
            'email'       => $user['email'],
            'role'        => $user['role'],
            'is_verified' => $verified
        ];

        return true;
    }

    /**
     * Register a buyer or a farmer.
     *
     * Farmers pass their farm name and location so a farm row can be
     * created right away with verification_status = 'pending'.
     */
    public static function register(
        string $name,
        string $email,
        string $password,
        string $role,
        string $farmName = '',
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

        $roleId = self::roleId($role);

        if ($roleId === null) {
            flash('error', 'Invalid account type.');
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            flash(
                'error',
                'An account with this email already exists.'
            );

            return false;
        }

        if ($role === 'farmer') {
            $farmName = trim($farmName);
            $location = trim($location);

            if ($farmName === '' || $location === '') {
                flash(
                    'error',
                    'Farm name and location are required to register as a farmer.'
                );

                return false;
            }
        }

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO users
                (
                    role_id,
                    name,
                    email,
                    password_hash
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $roleId,
                $name,
                $email,
                $hashedPassword
            ]);

            $userId = (int)$pdo->lastInsertId();

            if ($role === 'farmer') {
                $stmt = $pdo->prepare("
                    INSERT INTO farms
                    (
                        user_id,
                        farm_name,
                        location,
                        verification_status
                    )
                    VALUES (?, ?, ?, 'pending')
                ");

                $stmt->execute([
                    $userId,
                    $farmName,
                    $location
                ]);
            }

            $pdo->commit();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash('error', 'Registration failed. Please try again.');
            return false;
        }

        if ($role === 'farmer') {
            flash(
                'success',
                'Registration successful. Your farm is waiting for administrator verification.'
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