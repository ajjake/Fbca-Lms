<?php

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        self::start();
        return isset($_SESSION['user_id'], $_SESSION['role']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }
    }

    public static function requireRole(array $allowedRoles): void
    {
        self::requireLogin();

        if (!in_array($_SESSION['role'], $allowedRoles, true)) {
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
    }

    public static function getCurrentUserId(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function getCurrentUserRole(): ?string
    {
        self::start();
        return $_SESSION['role'] ?? null;
    }

    public static function getCurrentUserLevel(): int
    {
        self::start();
        return isset($_SESSION['level']) ? (int)$_SESSION['level'] : 1;
    }

    public static function setUser(array $user): void
    {
        self::start();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['level'] = (int) $user['level'];

        if (isset($user['avatar'])) {
            $_SESSION['avatar'] = $user['avatar'];
        }
    }

    public static function destroy(): void
    {
        self::start();

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
