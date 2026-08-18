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
    echo "       Configure backend/.env with valid DB_* vars and run php tools/setup/install.php.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const OUTDATED_SLUG_PARAM = ':slug';
const OUTDATED_VERSION_1_0 = '1.0.0';
const OUTDATED_VERSION_2_0 = '2.0.0';
const INSERT_INACTIVE_PLUGIN_SQL =
    "INSERT INTO plugins (slug, status, manifest_json)
     VALUES (:slug, 'inactive', CAST(:manifest AS jsonb))";

function insertInactivePlugin(PDO $pdo, string $slug, string $version, ?string $pluginName = null): void
{
    $manifest = json_encode([
        'name' => $pluginName ?? $slug,
        'label' => $pluginName ?? $slug,
        'version' => $version,
        'type' => 'entity',
        'core_version' => $version,
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $pdo->prepare(INSERT_INACTIVE_PLUGIN_SQL)->execute([
        OUTDATED_SLUG_PARAM => $slug,
        ':manifest' => $manifest,
    ]);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('getOutdated() returns plugin when disk version is greater', function () use ($pdo): void {
    $slug = 'test_outdated_' . bin2hex(random_bytes(3));
    insertInactivePlugin($pdo, $slug, OUTDATED_VERSION_1_0);

    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Test Outdated',
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
    insertInactivePlugin($pdo, $slug, OUTDATED_VERSION_1_0);

    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Test Equal',
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
    insertInactivePlugin($pdo, $slug, OUTDATED_VERSION_2_0);

    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Test Lower',
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

TestSuite::run('getOutdated() reports the plugin under its current slug after a rename (STORY 10.3)', function () use ($pdo): void {
    $pluginName = 'test_outdated_rename_' . bin2hex(random_bytes(3));
    $renamedSlug = $pluginName . '_renamed';
    insertInactivePlugin($pdo, $renamedSlug, OUTDATED_VERSION_1_0, $pluginName);

    $root = createPluginFixture([
        'name' => $pluginName,
        'label' => 'Test Outdated Renamed',
        'version' => OUTDATED_VERSION_2_0,
        'type' => 'entity',
        'core_version' => OUTDATED_VERSION_1_0,
    ]);

    try {
        $service = buildPluginOutdatedService($root, $pdo);
        $outdated = $service->getOutdated();

        $matching = null;
        foreach ($outdated as $item) {
            if ($item['slug'] === $renamedSlug) {
                $matching = $item;
            }
        }

        assertTrue($matching !== null, 'Should report the outdated instance under its current (renamed) slug');
        assertEquals(OUTDATED_VERSION_2_0, $matching['available_version'] ?? null, 'Available version must come from the disk manifest, resolved by plugin_name');

        foreach ($outdated as $item) {
            assertTrue($item['slug'] !== $pluginName, 'The stale (pre-rename) slug must never appear in the report');
        }
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
