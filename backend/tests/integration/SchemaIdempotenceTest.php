<?php

/**
 * SchemaIdempotenceTest — Verifies the base schema (backend/database/schema)
 * through the same code path the installer uses (SchemaInstaller over PDO,
 * no psql dependency): the files are discovered in order, applying them on
 * an already-installed database is idempotent (no errors, no data loss) and
 * every core table exists afterwards. Also covers the read-only surface of
 * DatabaseProvisioner (existence checks + identifier validation) without
 * creating anything on the server.
 *
 * Run:
 *   php backend/tests/integration/SchemaIdempotenceTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';

use Xestify\core\Database;
use Xestify\database\DatabaseProvisioner;
use Xestify\database\SchemaInstaller;
use Xestify\exceptions\DatabaseException;

const SCHEMA_DIR = BASE_PATH . '/database/schema';
const EXPECTED_SCHEMA_FILES = [
    '001_users.sql',
    '002_plugin_entity_data.sql',
    '003_plugins.sql',
    '004_plugin_extension_data.sql',
    '005_plugin_update_history.sql',
    '006_configuration.sql',
];
const EXPECTED_TABLES = [
    'users',
    'plugin_entity_data',
    'plugins',
    'plugin_extension_data',
    'plugin_update_history',
    'configuration',
];

// ---------------------------------------------------------------------------
// Load .env
// ---------------------------------------------------------------------------

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// ---------------------------------------------------------------------------
// Connectivity probe
// ---------------------------------------------------------------------------

try {
    Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all SchemaIdempotenceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run php tools/setup/install.php.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = \'public\' AND table_name = :table
        ) AS exists'
    );
    $stmt->execute([':table' => $table]);
    $row = $stmt->fetch();

    return $row !== false && $row['exists'] === true;
}

function buildProvisioner(): DatabaseProvisioner
{
    return new DatabaseProvisioner([
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? '5432',
        'user' => $_ENV['DB_USER'] ?? 'postgres',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'maintenance_db' => $_ENV['DB_NAME'] ?? 'xestify_dev',
    ]);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('SchemaInstaller lists the six schema files in order', function (): void {
    $installer = new SchemaInstaller(Database::connection(), SCHEMA_DIR);
    $files = array_map('basename', $installer->listFiles());
    assertEquals(EXPECTED_SCHEMA_FILES, $files, 'Schema files must be discovered in numeric order');
});

TestSuite::run('SchemaInstaller rejects a missing schema directory', function (): void {
    $installer = new SchemaInstaller(Database::connection(), BASE_PATH . '/database/does-not-exist');
    $threw = false;
    try {
        $installer->listFiles();
    } catch (DatabaseException) {
        $threw = true;
    }
    assertTrue($threw, 'A missing schema directory must raise DatabaseException');
});

TestSuite::run('re-applying the whole schema twice does not cause errors', function (): void {
    $installer = new SchemaInstaller(Database::connection(), SCHEMA_DIR);
    $first = $installer->apply();
    $second = $installer->apply();
    assertEquals(count(EXPECTED_SCHEMA_FILES), count($first), 'Every schema file must be applied');
    assertEquals(count(EXPECTED_SCHEMA_FILES), count($second), 'Every schema file must be re-applied');
    foreach ($second as $entry) {
        assertEquals('applied', $entry['status'], "{$entry['file']} must report applied");
    }
});

TestSuite::run('all core tables exist after applying the schema', function (): void {
    $pdo = Database::connection();
    foreach (EXPECTED_TABLES as $table) {
        assertTrue(tableExists($pdo, $table), "Table {$table} must exist");
    }
});

TestSuite::run('plugins.sort_order column exists', function (): void {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'plugins' AND column_name = 'sort_order'
        ) AS exists"
    );
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === true, 'Column plugins.sort_order must exist');
});

TestSuite::run('idempotent re-run preserves existing data', function (): void {
    $pdo = Database::connection();

    $testSlug = 'idempotence_test_' . uniqid();
    $manifest = json_encode([
        'name' => $testSlug,
        'label' => 'Idempotence Test Entity',
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare(
        'INSERT INTO plugins (slug, status, manifest_json)
         VALUES (:slug, :status, CAST(:manifest AS jsonb))
         ON CONFLICT (slug) DO NOTHING'
    )->execute([
        ':slug' => $testSlug,
        ':status' => 'active',
        ':manifest' => $manifest,
    ]);

    try {
        $count = $pdo->prepare('SELECT COUNT(*) AS cnt FROM plugins WHERE slug = :slug');
        $count->execute([':slug' => $testSlug]);
        assertEquals(1, (int) $count->fetchColumn(), 'Test row must be inserted');

        (new SchemaInstaller($pdo, SCHEMA_DIR))->apply();

        $count->execute([':slug' => $testSlug]);
        assertEquals(1, (int) $count->fetchColumn(), 'Test row must still be present; no duplicates should exist');
    } finally {
        $pdo->prepare('DELETE FROM plugins WHERE slug = :slug')->execute([':slug' => $testSlug]);
    }
});

TestSuite::run('DatabaseProvisioner detects the current role and database', function (): void {
    $provisioner = buildProvisioner();
    assertTrue($provisioner->databaseExists($_ENV['DB_NAME'] ?? 'xestify_dev'), 'Current database must be detected');
    assertTrue($provisioner->roleExists($_ENV['DB_USER'] ?? 'postgres'), 'Current role must be detected');
    assertFalse($provisioner->databaseExists('xestify_nonexistent_' . uniqid()), 'Unknown database must not exist');
    assertFalse($provisioner->roleExists('xestify_nonexistent_' . uniqid()), 'Unknown role must not exist');
});

TestSuite::run('DatabaseProvisioner rejects invalid identifiers before touching the server', function (): void {
    $provisioner = buildProvisioner();
    foreach (['bad-name', 'drop; table', '1starts_with_digit', '', 'with space', str_repeat('a', 64)] as $invalid) {
        $threw = false;
        try {
            $provisioner->ensureDatabase($invalid, null);
        } catch (DatabaseException) {
            $threw = true;
        }
        assertTrue($threw, "Identifier '{$invalid}' must be rejected");
    }
    $stillMissing = !$provisioner->databaseExists('bad-name');
    assertTrue($stillMissing, 'Nothing may be created for a rejected identifier');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
