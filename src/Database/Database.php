<?php
namespace App\Database;
use PDO;
class Database {
    private static $pdo;
    public static function connection() {
        if (self::$pdo) return self::$pdo;
        $host = getenv('DB_HOST') ?: 'db';
        $db = getenv('DB_NAME') ?: 'reminder';
        $user = getenv('DB_USER') ?: 'reminder';
        $pass = getenv('DB_PASS') ?: 'reminder_password';
        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return self::$pdo;
    }
}
