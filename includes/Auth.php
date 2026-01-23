<?php
require_once __DIR__ . '/Database.php';

class Auth
{
    const DEV_MODE = false;

    public static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login($username, $password)
    {
        self::startSession();
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            return true;
        }
        return false;
    }

    public static function logout()
    {
        self::startSession();
        session_destroy();
    }

    public static function isLoggedIn()
    {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin()
    {
        if (self::DEV_MODE) {
            self::startSession();
            // Simulate a session if not present
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['user_id'] = 999;
                $_SESSION['username'] = 'Dev';
            }
            return;
        }

        if (!self::isLoggedIn()) {
            header('Location: index.php?route=login');
            exit;
        }
    }

    public static function user()
    {
        self::startSession();
        if (self::DEV_MODE && !isset($_SESSION['username'])) {
            return 'Dev';
        }
        return $_SESSION['username'] ?? null;
    }

    public static function isAdmin()
    {
        self::startSession();
        if (self::DEV_MODE)
            return true;
        return (self::user() === 'admin');
    }

    public static function getLocationId()
    {
        self::startSession();
        return $_SESSION['location_id'] ?? 1; // Default to 1 (Hauptlager)
    }

    public static function setLocationId($id)
    {
        self::startSession();
        $_SESSION['location_id'] = intval($id);
        $_SESSION['location_selected'] = true;

        // Fetch name for display
        require_once __DIR__ . '/Database.php';
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['location_name'] = $stmt->fetchColumn() ?: 'Standort';
    }

    public static function isLocationSelected()
    {
        self::startSession();
        return !empty($_SESSION['location_selected']);
    }

    public static function clearLocationSelection()
    {
        self::startSession();
        unset($_SESSION['location_selected']);
    }

    public static function getCsrfToken()
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf($token)
    {
        self::startSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
