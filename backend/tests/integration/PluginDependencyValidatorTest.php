<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\PluginException;
use Xestify\plugins\infrastructure\PluginDependencyValidator;
use Xestify\repositories\PluginRepository;
use Xestify\plugins\infrastructure\PluginSchemaCodec;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginDependencyValidatorTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const DEP_VALIDATOR_VERSION = '1.0.0';

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
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version)
         VALUES (:slug, 'Dependency OK', 'extension', '1.0.0', 'inactive', 1)"
    )->execute([':slug' => $slug]);

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
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version)
         VALUES (:slug, 'Dependency Low', 'extension', '1.0.0', 'inactive', 1)"
    )->execute([':slug' => $slug]);

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

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
