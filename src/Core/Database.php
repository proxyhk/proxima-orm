<?php

namespace Proxima\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $config): void
    {
        if (self::$pdo !== null) return;

        // DSN oluştururken boşluklara dikkat etmemiz lazım, bazen hata yaptırır.
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $config['host'],
            $config['dbname'] ?? $config['db'],  // Support both 'dbname' and 'db'
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, $config['user'], $config['password'] ?? $config['pass'], [  // Support both 'password' and 'pass'
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Gerçek hayatta bunu loglamak gerekir ama şimdilik ekrana basalım
            die("Proxima Bağlantı Hatası: " . $e->getMessage());
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            throw new \Exception("Veritabanı bağlantısı yok! Proxima::connect() çağırdın mı?");
        }
        return self::$pdo;
    }
}