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
    echo "[SKIP] PostgreSQL not reachable — all PluginDeletionServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const DELETION_VERSION = '1.0.0';
const SQL_COUNT_PLUGIN_BY_SLUG = 'SELECT COUNT(*) AS cnt FROM plugins WHERE slug = :slug';
const SQL_DELETE_PLUGIN_BY_SLUG = 'DELETE FROM plugins WHERE slug = :slug';
const MSG_PLUGINS_ROW_GONE = 'plugins row must be gone';

/**
 * @return array<string, mixed> decoded row (manifest_json is a PHP array)
 */
function insertDeletionTestPlugin(PDO $pdo, string $pluginName, string $slug, string $pluginType, string $status): array
{
    $manifest = [
        'name' => $pluginName,
        'label' => 'Deletion Test',
        'version' => DELETION_VERSION,
        'type' => $pluginType,
        'core_version' => DELETION_VERSION,
        'description' => '',
    ];
    if ($pluginType === 'extension') {
        $manifest['target_entity'] = '*';
    }
    $schema = $pluginType === 'extension'
        ? ['fields' => []]
        : ['fields' => ['name' => ['type' => 'string', 'required' => true, 'label' => 'Name']], 'custom_fields' => [], 'relations' => []];

    $stmt = $pdo->prepare(
        'INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, :status, CAST(:manifest AS jsonb), CAST(:schema AS jsonb))
         RETURNING id, slug, status, manifest_json, schema_json'
    );
    $stmt->execute([
        ':slug' => $slug,
        ':status' => $status,
        ':manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':schema' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $decoded = json_decode((string) $row['manifest_json'], true);
    $row['manifest_json'] = is_array($decoded) ? $decoded : [];

    return $row;
}

function countRows(PDO $pdo, string $sql, array $params): string
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (string) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? '0');
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('deletePlugin() removes an inactive entity plugin and all its data', function () use ($pdo): void {
    $pluginName = 'test_delete_entity_' . bin2hex(random_bytes(3));
    $slug = $pluginName;
    insertDeletionTestPlugin($pdo, $pluginName, $slug, 'entity', 'inactive');

    $pdo->prepare("INSERT INTO plugin_entity_data (entity_slug, content) VALUES (:slug, '{}'::jsonb)")
        ->execute([':slug' => $slug]);

    $extPluginSlug = 'test_delete_ext_watcher_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
         VALUES (:plugin_slug, :entity_slug, gen_random_uuid(), :content::jsonb)'
    )->execute([':plugin_slug' => $extPluginSlug, ':entity_slug' => $slug, ':content' => '{}']);

    $historyManifest = json_encode(['name' => $pluginName, 'label' => 'x', 'version' => DELETION_VERSION, 'type' => 'entity', 'core_version' => DELETION_VERSION, 'description' => ''], JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        "INSERT INTO plugin_update_history (slug, status, manifest_json, target_version)
         VALUES (:slug, 'inactive', CAST(:manifest AS jsonb), :version)"
    )->execute([':slug' => $slug, ':manifest' => $historyManifest, ':version' => DELETION_VERSION]);

    try {
        $service = buildPluginAdministrationService(sys_get_temp_dir(), $pdo);
        $service->deletePlugin($slug);

        assertEquals('0', countRows($pdo, SQL_COUNT_PLUGIN_BY_SLUG, [':slug' => $slug]), MSG_PLUGINS_ROW_GONE);
        assertEquals('0', countRows($pdo, 'SELECT COUNT(*) AS cnt FROM plugin_entity_data WHERE entity_slug = :slug', [':slug' => $slug]), 'plugin_entity_data must be gone');
        assertEquals(
            '0',
            countRows($pdo, 'SELECT COUNT(*) AS cnt FROM plugin_extension_data WHERE entity_slug = :slug', [':slug' => $slug]),
            'plugin_extension_data pointing at this entity must be gone too, or it would be orphaned'
        );
        assertEquals('0', countRows($pdo, 'SELECT COUNT(*) AS cnt FROM plugin_update_history WHERE slug = :slug', [':slug' => $slug]), 'plugin_update_history must be gone');
    } finally {
        $pdo->prepare(SQL_DELETE_PLUGIN_BY_SLUG)->execute([':slug' => $slug]);
        $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug = :slug')->execute([':slug' => $slug]);
        $pdo->prepare('DELETE FROM plugin_extension_data WHERE entity_slug = :slug')->execute([':slug' => $slug]);
        $pdo->prepare('DELETE FROM plugin_update_history WHERE slug = :slug')->execute([':slug' => $slug]);
    }
});

TestSuite::run('deletePlugin() removes an extension plugin and only its own data', function () use ($pdo): void {
    $pluginName = 'test_delete_extension_' . bin2hex(random_bytes(3));
    $slug = $pluginName;
    $hostEntitySlug = 'test_delete_host_' . bin2hex(random_bytes(3));
    insertDeletionTestPlugin($pdo, $pluginName, $slug, 'extension', 'inactive');

    $pdo->prepare(
        'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
         VALUES (:plugin_slug, :entity_slug, gen_random_uuid(), :content::jsonb)'
    )->execute([':plugin_slug' => $slug, ':entity_slug' => $hostEntitySlug, ':content' => '{}']);

    $pdo->prepare("INSERT INTO plugin_entity_data (entity_slug, content) VALUES (:slug, '{}'::jsonb)")
        ->execute([':slug' => $hostEntitySlug]);

    try {
        $service = buildPluginAdministrationService(sys_get_temp_dir(), $pdo);
        $service->deletePlugin($slug);

        assertEquals('0', countRows($pdo, SQL_COUNT_PLUGIN_BY_SLUG, [':slug' => $slug]), MSG_PLUGINS_ROW_GONE);
        assertEquals(
            '0',
            countRows($pdo, 'SELECT COUNT(*) AS cnt FROM plugin_extension_data WHERE plugin_slug = :slug', [':slug' => $slug]),
            'this extension plugin\'s own data must be gone'
        );
        assertEquals(
            '1',
            countRows($pdo, 'SELECT COUNT(*) AS cnt FROM plugin_entity_data WHERE entity_slug = :slug', [':slug' => $hostEntitySlug]),
            'the host entity\'s own data must survive — deleting an extension must not touch entity data'
        );
    } finally {
        $pdo->prepare(SQL_DELETE_PLUGIN_BY_SLUG)->execute([':slug' => $slug]);
        $pdo->prepare('DELETE FROM plugin_extension_data WHERE plugin_slug = :slug')->execute([':slug' => $slug]);
        $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug = :slug')->execute([':slug' => $hostEntitySlug]);
    }
});

TestSuite::run('deletePlugin() deactivates an active plugin before deleting it', function () use ($pdo): void {
    $slug = 'test_delete_active_' . bin2hex(random_bytes(3));
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use Xestify\plugins\contracts\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void {}
    public function onDeactivate(): void { \$GLOBALS['delete_deactivate_called'] = true; }
}
PHP;
    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Delete Active',
        'version' => DELETION_VERSION,
        'type' => 'entity',
        'core_version' => DELETION_VERSION,
    ], false, false, null, $lifecycle);

    insertDeletionTestPlugin($pdo, $slug, $slug, 'entity', 'active');

    try {
        $GLOBALS['delete_deactivate_called'] = false;
        $service = buildPluginAdministrationService($root, $pdo);
        $service->deletePlugin($slug);

        assertTrue($GLOBALS['delete_deactivate_called'] === true, 'onDeactivate must fire before deleting an active plugin');
        assertEquals('0', countRows($pdo, SQL_COUNT_PLUGIN_BY_SLUG, [':slug' => $slug]), MSG_PLUGINS_ROW_GONE);
    } finally {
        $pdo->prepare(SQL_DELETE_PLUGIN_BY_SLUG)->execute([':slug' => $slug]);
        removePluginFixture($root);
    }
});

TestSuite::run('deletePlugin() throws for an unknown slug', function () use ($pdo): void {
    $service = buildPluginAdministrationService(sys_get_temp_dir(), $pdo);
    $threw = false;

    try {
        $service->deletePlugin('nonexistent_slug_xyz_' . bin2hex(random_bytes(3)));
    } catch (OutOfBoundsException) {
        $threw = true;
    }

    assertTrue($threw, 'Deleting an unknown slug must throw OutOfBoundsException');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
