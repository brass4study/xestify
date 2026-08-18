<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\PluginException;
use Xestify\plugins\guards\PluginDependencyValidator;
use Xestify\repositories\PluginRepository;
use Xestify\plugins\discovery\PluginSchemaCodec;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginDependencyValidatorTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run php tools/setup/install.php.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const DEP_VALIDATOR_VERSION = '1.0.0';

function insertDependencyTestPlugin(PDO $pdo, string $slug, string $label, ?string $pluginName = null): void
{
    $manifest = json_encode([
        'name' => $pluginName ?? $slug,
        'label' => $label,
        'version' => DEP_VALIDATOR_VERSION,
        'type' => 'extension',
        'core_version' => DEP_VALIDATOR_VERSION,
        'target_entity' => '*',
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $pdo->prepare(
        "INSERT INTO plugins (slug, status, manifest_json)
         VALUES (:slug, 'inactive', CAST(:manifest AS jsonb))"
    )->execute([':slug' => $slug, ':manifest' => $manifest]);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('validate() accepts empty dependencies', function () use ($pdo): void {
    $validator = new PluginDependencyValidator(new PluginRepository($pdo, new PluginSchemaCodec()));
    $validator->validate(['slug' => 'dep-empty']);
    assertTrue(true, 'Empty dependencies should pass');
});

TestSuite::run('validate() fails when dependency is missing', function () use ($pdo): void {
    $validator = new PluginDependencyValidator(new PluginRepository($pdo, new PluginSchemaCodec()));
    $threw = false;

    try {
        $validator->validate([
            'slug' => 'dep-missing',
            'requires' => [['slug' => 'dep-not-installed', 'version' => DEP_VALIDATOR_VERSION]],
        ]);
    } catch (PluginException $e) {
        $threw = str_contains($e->getMessage(), 'not installed');
    }

    assertTrue($threw, 'Missing dependency must fail validation');
});

TestSuite::run('validate() accepts installed dependency with enough version', function () use ($pdo): void {
    $slug = 'depok' . bin2hex(random_bytes(3));
    insertDependencyTestPlugin($pdo, $slug, 'Dependency OK');

    try {
        $validator = new PluginDependencyValidator(new PluginRepository($pdo, new PluginSchemaCodec()));
        $validator->validate([
            'slug' => 'dep-parent',
            'requires' => [['slug' => $slug, 'version' => DEP_VALIDATOR_VERSION]],
        ]);
        assertTrue(true, 'Installed dependency should pass');
    } finally {
        cleanupPluginRecord($pdo, $slug);
    }
});

TestSuite::run('validate() fails when dependency version is too low', function () use ($pdo): void {
    $slug = 'deplow' . bin2hex(random_bytes(3));
    insertDependencyTestPlugin($pdo, $slug, 'Dependency Low');

    try {
        $validator = new PluginDependencyValidator(new PluginRepository($pdo, new PluginSchemaCodec()));
        $threw = false;

        try {
            $validator->validate([
                'slug' => 'dep-parent',
                'requires' => [['slug' => $slug, 'version' => '2.0.0']],
            ]);
        } catch (PluginException $e) {
            $threw = str_contains($e->getMessage(), '2.0.0');
        }

        assertTrue($threw, 'Dependency version lower than required must fail');
    } finally {
        cleanupPluginRecord($pdo, $slug);
    }
});

TestSuite::run('validate() keeps resolving a dependency by plugin_name after its slug was renamed (STORY 10.3)', function () use ($pdo): void {
    $pluginName = 'depname' . bin2hex(random_bytes(3));
    $renamedSlug = $pluginName . '_renamed';
    insertDependencyTestPlugin($pdo, $renamedSlug, 'Dependency Renamed', $pluginName);

    try {
        $validator = new PluginDependencyValidator(new PluginRepository($pdo, new PluginSchemaCodec()));
        $validator->validate([
            'slug' => 'dep-parent',
            'requires' => [['slug' => $pluginName, 'version' => DEP_VALIDATOR_VERSION]],
        ]);
        assertTrue(true, 'A renamed dependency must still resolve by its fixed plugin_name');
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
