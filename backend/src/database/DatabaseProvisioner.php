<?php

declare(strict_types=1);

namespace Xestify\database;

use PDO;
use PDOException;
use Xestify\exceptions\DatabaseException;

/**
 * Creates the application's PostgreSQL role and database when they do not
 * exist yet (`tools/setup/install.php --create-db`). Talks to the server
 * through its own connection to the maintenance database (`postgres` by
 * default) with maintenance credentials — never through the application's
 * `Database` singleton, which targets the (possibly still missing) app DB.
 *
 * `CREATE ROLE` / `CREATE DATABASE` cannot take bound parameters, so
 * identifiers are validated against a strict pattern before being quoted and
 * the role password goes through PDO::quote(). Anything failing validation is
 * rejected before touching the server.
 */
final class DatabaseProvisioner
{
    private const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    private const IDENTIFIER_MAX_LENGTH = 63;
    private const DEFAULT_MAINTENANCE_DB = 'postgres';

    private ?PDO $pdo = null;

    /**
     * @param array{host: string, port: string, user: string, password: string, maintenance_db?: string} $connection
     */
    public function __construct(private array $connection)
    {
    }

    public function roleExists(string $role): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM pg_roles WHERE rolname = :name');
        $stmt->execute([':name' => $role]);

        return $stmt->fetchColumn() !== false;
    }

    public function databaseExists(string $database): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM pg_database WHERE datname = :name');
        $stmt->execute([':name' => $database]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Creates a LOGIN role with the given password unless it already exists.
     * Returns true when the role was created, false when it already existed.
     */
    public function ensureRole(string $role, string $password): bool
    {
        self::assertIdentifier($role, 'role');

        if ($this->roleExists($role)) {
            return false;
        }

        $this->execute(
            'CREATE ROLE ' . self::quoteIdentifier($role)
                . ' LOGIN PASSWORD ' . $this->pdo()->quote($password),
            "Could not create role '{$role}'"
        );

        return true;
    }

    /**
     * Creates the database (owned by $owner when given) unless it already
     * exists. Returns true when created, false when it already existed.
     */
    public function ensureDatabase(string $database, ?string $owner = null): bool
    {
        self::assertIdentifier($database, 'database');
        if ($owner !== null) {
            self::assertIdentifier($owner, 'owner');
        }

        if ($this->databaseExists($database)) {
            return false;
        }

        $sql = 'CREATE DATABASE ' . self::quoteIdentifier($database);
        if ($owner !== null) {
            $sql .= ' OWNER ' . self::quoteIdentifier($owner);
        }

        $this->execute($sql, "Could not create database '{$database}'");

        return true;
    }

    /**
     * Validates a PostgreSQL identifier (role/database name) before it is
     * interpolated into DDL. Public so callers can fail fast on user input.
     */
    public static function assertIdentifier(string $value, string $what): void
    {
        if (
            $value === ''
            || strlen($value) > self::IDENTIFIER_MAX_LENGTH
            || preg_match(self::IDENTIFIER_PATTERN, $value) !== 1
        ) {
            throw new DatabaseException(
                "Invalid {$what} name '{$value}': use only letters, digits and underscores, "
                . 'starting with a letter or underscore (max ' . self::IDENTIFIER_MAX_LENGTH . ' chars).'
            );
        }
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function execute(string $sql, string $failureMessage): void
    {
        try {
            $this->pdo()->exec($sql);
        } catch (PDOException $e) {
            throw new DatabaseException($failureMessage . ': ' . $e->getMessage(), 0, $e);
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $host = $this->connection['host'];
        $port = $this->connection['port'];
        $maintenanceDb = $this->connection['maintenance_db'] ?? self::DEFAULT_MAINTENANCE_DB;
        $dsn = "pgsql:host={$host};port={$port};dbname={$maintenanceDb};options='--client_encoding=UTF8'";

        try {
            $this->pdo = new PDO($dsn, $this->connection['user'], $this->connection['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Could not connect to maintenance database '{$maintenanceDb}' as '{$this->connection['user']}': "
                . $e->getMessage(),
                0,
                $e
            );
        }

        return $this->pdo;
    }
}
