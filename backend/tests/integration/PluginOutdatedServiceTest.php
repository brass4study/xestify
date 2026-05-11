<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_services.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginOutdatedServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const OUTDATED_SLUG_PARAM = ':slug';
const OUTDATED_VERSION_1_0 = '1.0.0';
const OUTDATED_VERSION_2_0 = '2.0.0';

echo str_repeat('-', 40) . "\n";

TestSuite::run('getOutdated() returns plugin when disk version is greater', function () use ($pdo): void {
    $slug = 'test_outdated_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, plugin_type, version, status)
         VALUES (:slug, 'entity', :version, 'inactive')"
    )->execute([OUTDATED_SLUG_PARAM => $slug, ':version' => OUTDATED_VERSION_1_0]);

    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Test Outdated',
        'version' => OUTDATED_VERSION_2_0,
        'type' => 'entity',
        'core_version' => OUTDATED_VERSION_1_0,
    ]);

    try {
        $service = buildPluginOutdatedService($root, $pdo);
        $outdated = $service->getOutdated();

        $found = false;
        foreach ($outdated as $item) {
            if ($item['slug'] === $slug) {
                $found = true;
            }
        }

        assertTrue($found, 'Should report plugin when disk version is greater');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('getOutdated() ignores plugin when disk version is equal', function () use ($pdo): void {
    $slug = 'test_equal_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, plugin_type, version, status)
         VALUES (:slug, 'entity', :version, 'inactive')"
    )->execute([OUTDATED_SLUG_PARAM => $slug, ':version' => OUTDATED_VERSION_1_0]);

    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Test Equal',
        'version' => OUTDATED_VERSION_1_0,
        'type' => 'entity',
        'core_version' => OUTDATED_VERSION_1_0,
    ]);

    try {
        $service = buildPluginOutdatedService($root, $pdo);
        $outdated = $service->getOutdated();

        foreach ($outdated as $item) {
            assertTrue($item['slug'] !== $slug, 'Should not report equal version');
        }
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('getOutdated() ignores plugin when disk version is lower', function () use ($pdo): void {
    $slug = 'test_lower_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, plugin_type, version, status)
         VALUES (:slug, 'entity', :version, 'inactive')"
    )->execute([OUTDATED_SLUG_PARAM => $slug, ':version' => OUTDATED_VERSION_2_0]);

    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Test Lower',
        'version' => OUTDATED_VERSION_1_0,
        'type' => 'entity',
        'core_version' => OUTDATED_VERSION_1_0,
    ]);

    try {
        $service = buildPluginOutdatedService($root, $pdo);
        $outdated = $service->getOutdated();

        foreach ($outdated as $item) {
            assertTrue($item['slug'] !== $slug, 'Should not report lower disk version');
        }
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
