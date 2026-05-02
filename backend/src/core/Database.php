<?php

declare(strict_types=1);

namespace Xestify\core;

use PDO;
use PDOException;
use Xestify\exceptions\DatabaseException;

/**
 * Singleton PDO wrapper.
 * Reads connection params from $_ENV (loaded by bootstrap.php).
 */
class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
        // Singleton ÔÇö instantiation prevented intentionally.
    }

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::createConnection();
        }
        return self::$pdo;
    }

    private static function createConnection(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $name = $_ENV['DB_NAME'] ?? 'xestify_dev';
        $user = $_ENV['DB_USER'] ?? 'postgres';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$name};options='--client_encoding=UTF8'";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new DatabaseException('Could not connect to database: ' . $e->getMessage());
        }

        return $pdo;
    }
}
