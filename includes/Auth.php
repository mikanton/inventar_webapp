<?php
require_once __DIR__ . '/Database.php';

class Auth
{
    // Secure Session Start
    public static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure cookie params
            session_set_cookie_params([
                'lifetime' => 0, // Until browser close
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']), // True if HTTPS
                'httponly' => true, // No JS access
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    public static function login($username, $password)
    {
        self::startSession();
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Stop Session Fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $user['role'] ?: 'user';

            // Set CSRF token immediately
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return true;
        }
        return false;
    }

    public static function logout()
    {
        self::startSession();
        // Clear all session data
        $_SESSION = [];
        // Delete cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function isLoggedIn()
    {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin()
    {
        if (!self::isLoggedIn()) {
            header('Location: index.php?route=login');
            exit;
        }
    }

    public static function user()
    {
        self::startSession();
        return $_SESSION['username'] ?? null;
    }

    public static function isAdmin()
    {
        self::startSession();
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    // --- Location Handling ---
    public static function getLocationId()
    {
        self::startSession();
        return $_SESSION['location_id'] ?? 1;
    }

    public static function setLocationId($id)
    {
        self::startSession();
        $_SESSION['location_id'] = intval($id);
        $_SESSION['location_selected'] = true;

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

    // --- CSRF ---
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
