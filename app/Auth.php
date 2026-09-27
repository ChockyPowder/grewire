<?php

declare(strict_types=1);

namespace Grewire;

use PDO;
use RuntimeException;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('grewire_session');
            session_set_cookie_params([
                'httponly' => true,
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'samesite' => 'Lax',
                'path' => '/',
            ]);
            session_start();
        }
    }

    public static function user(PDO $pdo): ?array
    {
        self::start();

        $id = $_SESSION['user_id'] ?? null;
        if (!is_string($id) || $id === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, username, display_name, created_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            self::logout();
            return null;
        }

        return $user;
    }

    public static function requireUser(PDO $pdo): array
    {
        $user = self::user($pdo);
        if (!$user) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
            }
            header('Location: /login.php');
            exit;
        }
        return $user;
    }

    public static function login(PDO $pdo, string $username, string $password): array
    {
        self::start();

        $stmt = $pdo->prepare(
            'SELECT id, username, display_name, password_hash
             FROM users WHERE LOWER(username) = LOWER(:username) LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Invalid username or password.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];

        unset($user['password_hash']);
        return $user;
    }

    public static function register(PDO $pdo, string $username, string $displayName, string $password): array
    {
        self::start();

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, display_name, password_hash)
             VALUES (:username, :display_name, :password_hash)
             RETURNING id, username, display_name'
        );

        try {
            $stmt->execute([
                'username' => $username,
                'display_name' => $displayName,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                throw new RuntimeException('That username is already taken.');
            }
            throw $exception;
        }

        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('Unable to create account.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];

        return $user;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}
