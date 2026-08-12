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
    echo "[SKIP] PostgreSQL not reachable — all PluginStatusServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const STATUS_QUERY = 'SELECT status FROM plugins WHERE slug = :slug';

echo str_repeat('-', 40) . "\n";

TestSuite::run('activate() updates plugin status to active', function () use ($pdo): void {
    $slug = 'status' . bin2hex(random_bytes(3));
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use Xestify\plugins\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void { \$GLOBALS['status_activate'] = (\$GLOBALS['status_activate'] ?? 0) + 1; }
    public function onDeactivate(): void { \$GLOBALS['status_deactivate'] = (\$GLOBALS['status_deactivate'] ?? 0) + 1; }
}
PHP;
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Status Plugin',
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
    ], false, false, null, $lifecycle);
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Status Plugin', 'entity', '1.0.0', 'inactive', 1, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => '{"fields":{"name":{"type":"string","required":true}}}',
    ]);

    try {
        $GLOBALS['status_activate'] = 0;
        $service = buildPluginStatusService($root, $pdo);
        $service->activate($slug);

        $stmt = $pdo->prepare(STATUS_QUERY);
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertEquals('active', $row['status'] ?? null, 'status must be active after activate()');
        assertEquals(1, $GLOBALS['status_activate'] ?? 0, 'onActivate must be called once');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('deactivate() updates plugin status to inactive', function () use ($pdo): void {
    $slug = 'status' . bin2hex(random_bytes(3));
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use Xestify\plugins\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void {}
    public function onDeactivate(): void { \$GLOBALS['status_deactivate'] = (\$GLOBALS['status_deactivate'] ?? 0) + 1; }
}
PHP;
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Status Plugin',
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
    ], false, false, null, $lifecycle);
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Status Plugin', 'entity', '1.0.0', 'active', 1, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => '{"fields":{"name":{"type":"string","required":true}}}',
    ]);

    try {
        $GLOBALS['status_deactivate'] = 0;
        $service = buildPluginStatusService($root, $pdo);
        $service->deactivate($slug);

        $stmt = $pdo->prepare(STATUS_QUERY);
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertEquals('inactive', $row['status'] ?? null, 'status must be inactive after deactivate()');
        assertEquals(1, $GLOBALS['status_deactivate'] ?? 0, 'onDeactivate must be called once');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('activate() rolls back status when onActivate fails', function () use ($pdo): void {
    $slug = 'status' . bin2hex(random_bytes(3));
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use RuntimeException;
use Xestify\plugins\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void { throw new RuntimeException('activate failed on purpose'); }
    public function onDeactivate(): void {}
}
PHP;
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Status Plugin',
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
    ], false, false, null, $lifecycle);
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Status Plugin', 'entity', '1.0.0', 'inactive', 1, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => '{"fields":{"name":{"type":"string","required":true}}}',
    ]);

    try {
        $service = buildPluginStatusService($root, $pdo);
        $threw = false;
        try {
            $service->activate($slug);
        } catch (RuntimeException $e) {
            $threw = str_contains($e->getMessage(), 'on purpose');
        }

        assertTrue($threw, 'activate() should bubble lifecycle failure');

        $stmt = $pdo->prepare(STATUS_QUERY);
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertEquals(
            'inactive',
            $row['status'] ?? null,
            'status must roll back to inactive when onActivate fails (04.04)'
        );
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
