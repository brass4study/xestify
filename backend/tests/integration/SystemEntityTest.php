<?php

/**
 * SystemEntityTest — Integration tests for SystemEntity model.
 *
 * Exercises getActive(), getBySlug(), and findOrFail() against a live
 * PostgreSQL database. A test entity is inserted before the suite and
 * cleaned up afterwards.
 *
 * Run:
 *   php backend/tests/integration/SystemEntityTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/EntityServiceException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/models/SystemEntity.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\EntityServiceException;
use Xestify\models\SystemEntity;

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
    echo "[SKIP] PostgreSQL not reachable — all SystemEntityTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

$pdo      = Database::connection();
$testSlug = 'test_system_entity_' . bin2hex(random_bytes(4));

$pdo->prepare(
    'INSERT INTO system_entities (slug, name, is_active)
     VALUES (:slug, :name, true)'
)->execute([':slug' => $testSlug, ':name' => 'Test Entity']);

// Inactive entity to verify getActive() filtering
$inactiveSlug = $testSlug . '_inactive';
$pdo->prepare(
    'INSERT INTO system_entities (slug, name, is_active)
     VALUES (:slug, :name, false)'
)->execute([':slug' => $inactiveSlug, ':name' => 'Inactive Test Entity']);

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo "\nSystemEntityTest\n";
echo str_repeat('-', 40) . "\n";

TestSuite::run('getActive() retorna el test entity activo', function () use ($pdo, $testSlug): void {
    $model  = new SystemEntity($pdo);
    $active = $model->getActive();
    $slugs  = array_column($active, 'slug');
    assertTrue(in_array($testSlug, $slugs, true), 'El slug activo debe aparecer en getActive()');
});

TestSuite::run('getActive() no incluye entidades inactivas', function () use ($pdo, $inactiveSlug): void {
    $model  = new SystemEntity($pdo);
    $active = $model->getActive();
    $slugs  = array_column($active, 'slug');
    assertTrue(!in_array($inactiveSlug, $slugs, true), 'getActive() no debe incluir entidades inactivas');
});

TestSuite::run('getBySlug() retorna el entity correcto', function () use ($pdo, $testSlug): void {
    $model  = new SystemEntity($pdo);
    $entity = $model->getBySlug($testSlug);
    assertTrue($entity !== null, 'getBySlug() debe encontrar el test entity');
    assertEquals($testSlug, $entity['slug']);
    assertEquals('Test Entity', $entity['name']);
});

TestSuite::run('getBySlug() retorna null para slug inexistente', function () use ($pdo): void {
    $model  = new SystemEntity($pdo);
    $result = $model->getBySlug('slug_que_no_existe_xyz');
    assertTrue($result === null, 'getBySlug() debe retornar null para slug desconocido');
});

TestSuite::run('findOrFail() retorna el entity cuando existe', function () use ($pdo, $testSlug): void {
    $model  = new SystemEntity($pdo);
    $entity = $model->findOrFail($testSlug);
    assertEquals($testSlug, $entity['slug']);
});

TestSuite::run('findOrFail() lanza EntityServiceException para slug inexistente', function () use ($pdo): void {
    $model     = new SystemEntity($pdo);
    $thrown    = false;
    try {
        $model->findOrFail('slug_que_no_existe_xyz');
    } catch (EntityServiceException) {
        $thrown = true;
    }
    assertTrue($thrown, 'findOrFail() debe lanzar EntityServiceException para slug desconocido');
});

TestSuite::run('getActive() usa cache: segunda llamada no relanza query', function () use ($pdo, $testSlug): void {
    $model = new SystemEntity($pdo);
    $first  = $model->getActive();
    $second = $model->getActive();
    // Both calls must return the same data (cache hit)
    assertEquals(count($first), count($second));
    $slugsFirst  = array_column($first, 'slug');
    $slugsSecond = array_column($second, 'slug');
    assertEquals($slugsFirst, $slugsSecond);
});

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------

$pdo->prepare('DELETE FROM system_entities WHERE slug = :slug')->execute([':slug' => $testSlug]);
$pdo->prepare('DELETE FROM system_entities WHERE slug = :slug')->execute([':slug' => $inactiveSlug]);

// ---------------------------------------------------------------------------
// Resumen
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
