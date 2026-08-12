<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/repositories/ConfigurationRepository.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\repositories\ConfigurationRepository;

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

try {
    Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all ConfigurationRepositoryTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run the migrations in order (001-006).\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

TestSuite::run('configuration migration file exists', function (): void {
    $sql = file_get_contents(BASE_PATH . '/database/migrations/006_configuration.sql');
    assertTrue($sql !== false, 'Configuration migration should be readable');
    assertTrue(str_contains($sql, 'CREATE TABLE IF NOT EXISTS configuration'), 'Migration should create configuration table');
});

TestSuite::run('ConfigurationRepository saves and retrieves ui-preferences', function (): void {
    $pdo = Database::connection();
    $repo = new ConfigurationRepository($pdo);
    $key = 'ui-preferences';
    $backup = $repo->find($key);

    try {
        $saved = $repo->save($key, [
            'navigationMode' => 'side',
            'contentWidth' => 'fluid',
            'tokens' => ['accentColor' => 'cyan'],
        ]);
        $found = $repo->find($key);

        assertEquals($key, $saved['key'], 'Saved key mismatch');
        assertEquals('side', $saved['value']['navigationMode'] ?? null, 'Saved navigation mode mismatch');
        assertEquals('fluid', $found['value']['contentWidth'] ?? null, 'Found content width mismatch');
        assertEquals('cyan', $found['value']['tokens']['accentColor'] ?? null, 'Found nested token mismatch');
    } finally {
        if ($backup === null) {
            $pdo->prepare('DELETE FROM configuration WHERE config_key = :config_key')->execute([':config_key' => $key]);
        } else {
            $repo->save($key, $backup['value']);
        }
    }
});

TestSuite::summary();
exit(TestSuite::exitCode());

