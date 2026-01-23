<?php
require_once __DIR__ . '/Database.php';

class UserManager
{
    public static function create($username, $password)
    {
        $pdo = Database::connect();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
            $stmt->execute([$username, $hash]);
            return true;
        } catch (PDOException $e) {
            return false; // Likely duplicate username
        }
    }

    public static function delete($id)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function list()
    {
        $pdo = Database::connect();
        return $pdo->query("SELECT id, username, created_at FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function changePassword($id, $newPassword)
    {
        $pdo = Database::connect();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $id]);
        return $stmt->rowCount() > 0;
    }
}
