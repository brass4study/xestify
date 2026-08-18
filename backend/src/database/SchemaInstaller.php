<?php

declare(strict_types=1);

namespace Xestify\database;

use PDO;
use PDOException;
use Xestify\exceptions\DatabaseException;

/**
 * Applies the initial database schema: every `*.sql` file under
 * `backend/database/schema/`, in file-name order, through the same PDO
 * connection the application uses (no `psql` dependency). Each file runs in
 * its own transaction, so a failing file leaves the database exactly as it
 * was before that file. The files themselves are idempotent (CREATE ... IF NOT
 * EXISTS), so applying the schema on an already-installed database is a no-op
 * — that is what makes `tools/setup/install.php` safe to re-run.
 *
 * `backend/database/migrations/` (incremental changes for existing installs)
 * is deliberately NOT applied here; see its README.
 */
final class SchemaInstaller
{
    private const SQL_EXTENSION = 'sql';

    public function __construct(private PDO $pdo, private string $schemaDir)
    {
    }

    /**
     * Absolute paths of the schema files, sorted by file name (natural order,
     * so `010_x.sql` sorts after `009_x.sql`).
     *
     * @return list<string>
     */
    public function listFiles(): array
    {
        if (!is_dir($this->schemaDir)) {
            throw new DatabaseException("Schema directory not found: {$this->schemaDir}");
        }

        $entries = scandir($this->schemaDir, SCANDIR_SORT_NONE);
        if ($entries === false) {
            throw new DatabaseException("Schema directory is not readable: {$this->schemaDir}");
        }

        $files = [];
        foreach ($entries as $entry) {
            $path = $this->schemaDir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === self::SQL_EXTENSION) {
                $files[] = $path;
            }
        }

        if ($files === []) {
            throw new DatabaseException("No .sql files found in schema directory: {$this->schemaDir}");
        }

        sort($files, SORT_NATURAL);

        return $files;
    }

    /**
     * Applies every schema file in order. Stops at the first failure (after
     * rolling back that file's transaction) and rethrows as DatabaseException
     * naming the offending file.
     *
     * @return list<array{file: string, status: string, duration_ms: int}>
     */
    public function apply(): array
    {
        $report = [];

        foreach ($this->listFiles() as $path) {
            $startedAt = hrtime(true);
            $this->applyFile($path);
            $report[] = [
                'file' => basename($path),
                'status' => 'applied',
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ];
        }

        return $report;
    }

    private function applyFile(string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new DatabaseException('Cannot read schema file: ' . basename($path));
        }

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec($sql);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new DatabaseException(
                'Schema file ' . basename($path) . ' failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
