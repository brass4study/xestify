<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_services.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\PluginException;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginDependenciesTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const DEP_VERSION = '1.0.0';

function depRoot(string $suffix): string
{
    $root = sys_get_temp_dir() . '/xestify_dep_' . $suffix . '_' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);
    return $root;
}

function createDepFixture(string $baseDir, string $slug, string $version = DEP_VERSION, array $requires = []): void
{
    $dir = $baseDir . '/' . $slug;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $manifest = [
        'slug' => $slug,
        'name' => 'Dep Test Plugin ' . $slug,
        'version' => $version,
        'type' => 'entity',
        'core_version' => DEP_VERSION,
    ];

    if ($requires !== []) {
        $manifest['requires'] = $requires;
    }

    file_put_contents($dir . '/manifest.json', (string) json_encode($manifest));
    file_put_contents($dir . '/schema.json', (string) json_encode([
        'entity' => $slug,
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]));
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('syncAll() con requires vacío carga el plugin sin error', function () use ($pdo): void {
    $slug = 'depa' . bin2hex(random_bytes(3));
    $root = depRoot('empty');
    createDepFixture($root, $slug);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();
        assertEquals('registered', $result['plugins'][$slug]['result'] ?? null, 'plugin should be registered');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() falla si plugin requerido no está instalado', function () use ($pdo): void {
    $slugA = 'depa' . bin2hex(random_bytes(3));
    $slugB = 'depb' . bin2hex(random_bytes(3));
    $root = depRoot('missing');
    createDepFixture($root, $slugB, DEP_VERSION, [['slug' => $slugA, 'version' => DEP_VERSION]]);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();
        assertEquals('error', $result['plugins'][$slugB]['result'] ?? null, 'missing dependency should fail');
    } finally {
        cleanupPluginRecord($pdo, $slugA);
        cleanupPluginRecord($pdo, $slugB);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() carga plugin cuando dependencia ya está instalada', function () use ($pdo): void {
    $slugA = 'depa' . bin2hex(random_bytes(3));
    $slugB = 'depb' . bin2hex(random_bytes(3));
    $root = depRoot('installed');
    createDepFixture($root, $slugA);
    createDepFixture($root, $slugB, DEP_VERSION, [['slug' => $slugA, 'version' => DEP_VERSION]]);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();
        assertTrue(isset($result['plugins'][$slugA]), 'dependency plugin should be processed');
        assertTrue(isset($result['plugins'][$slugB]), 'dependent plugin should be processed');
        assertEquals('registered', $result['plugins'][$slugB]['result'] ?? null, 'dependent plugin should register');
    } finally {
        cleanupPluginRecord($pdo, $slugA);
        cleanupPluginRecord($pdo, $slugB);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() falla si versión instalada es menor a la requerida', function () use ($pdo): void {
    $slugA = 'depa' . bin2hex(random_bytes(3));
    $slugB = 'depb' . bin2hex(random_bytes(3));
    $root = depRoot('version');
    createDepFixture($root, $slugA);
    createDepFixture($root, $slugB, DEP_VERSION, [['slug' => $slugA, 'version' => '2.0.0']]);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();
        assertEquals('registered', $result['plugins'][$slugA]['result'] ?? null, 'dependency should register');
        assertEquals('error', $result['plugins'][$slugB]['result'] ?? null, 'dependent plugin should fail');
    } finally {
        cleanupPluginRecord($pdo, $slugA);
        cleanupPluginRecord($pdo, $slugB);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() reporta requires inválido cuando falta slug', function () use ($pdo): void {
    $slug = 'depbad' . bin2hex(random_bytes(3));
    $root = depRoot('invalid');
    createDepFixture($root, $slug);
    file_put_contents($root . '/' . $slug . '/manifest.json', (string) json_encode([
        'slug' => $slug,
        'name' => 'Bad Dep Plugin',
        'version' => DEP_VERSION,
        'type' => 'entity',
        'core_version' => DEP_VERSION,
        'requires' => [['version' => DEP_VERSION]],
    ]));

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();
        assertEquals('error', $result['plugins'][$slug]['result'] ?? null, 'invalid requires should fail');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
