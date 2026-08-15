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
    echo "[SKIP] PostgreSQL not reachable — all PluginRollbackServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const ROLLBACK_SLUG_PARAM = ':slug';
const ROLLBACK_V1 = '1.0.0';
const ROLLBACK_V2 = '2.0.0';
const ROLLBACK_PLUGIN_LABEL = 'Rollback Plugin';

/**
 * @param array<string, mixed> $manifestOverrides
 * @param array<string, mixed>|null $schema
 */
function insertRollbackTestPlugin(PDO $pdo, string $slug, array $manifestOverrides, string $status, ?array $schema): void
{
    $manifest = array_merge([
        'name' => $slug,
        'label' => $slug,
        'version' => ROLLBACK_V1,
        'type' => 'entity',
        'core_version' => ROLLBACK_V1,
        'description' => '',
    ], $manifestOverrides);

    $pdo->prepare(
        'INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, :status, CAST(:manifest AS jsonb), CAST(:schema AS jsonb))'
    )->execute([
        ':slug' => $slug,
        ':status' => $status,
        ':manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':schema' => $schema === null ? null : json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/**
 * @param array<string, mixed> $manifestOverrides
 * @param array<string, mixed>|null $schema
 */
function insertRollbackSnapshot(PDO $pdo, string $slug, array $manifestOverrides, string $status, ?array $schema, string $targetVersion): void
{
    $manifest = array_merge([
        'name' => $slug,
        'label' => $slug,
        'version' => ROLLBACK_V1,
        'type' => 'entity',
        'core_version' => ROLLBACK_V1,
        'description' => '',
    ], $manifestOverrides);

    $pdo->prepare(
        'INSERT INTO plugin_update_history (slug, status, manifest_json, schema_json, target_version)
         VALUES (:slug, :status, CAST(:manifest AS jsonb), CAST(:schema AS jsonb), :target)'
    )->execute([
        ':slug' => $slug,
        ':status' => $status,
        ':manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':schema' => $schema === null ? null : json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':target' => $targetVersion,
    ]);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('rollback() restores snapshot version and executes onRollback when available', function () use ($pdo): void {
    $slug = 'test_rollback_ok_' . bin2hex(random_bytes(3));

    $currentSchema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            'email' => ['type' => 'email', 'required' => false, 'label' => 'Email'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];

    $snapshotSchema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];

    insertRollbackTestPlugin($pdo, $slug, ['label' => ROLLBACK_PLUGIN_LABEL, 'version' => ROLLBACK_V2], 'active', $currentSchema);
    insertRollbackSnapshot($pdo, $slug, ['label' => ROLLBACK_PLUGIN_LABEL, 'version' => ROLLBACK_V1], 'active', $snapshotSchema, ROLLBACK_V2);

    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use Xestify\plugins\contracts\PluginLifecycleUpdateInterface;
final class Lifecycle implements PluginLifecycleUpdateInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void {}
    public function onDeactivate(): void {}
    public function onUpdate(array \$context): void {}
    public function onRollback(array \$context): void { \$GLOBALS['rollback_to_version'] = \$context['to_version'] ?? null; }
}
PHP;
    $root = createPluginFixture([
        'name' => $slug,
        'label' => ROLLBACK_PLUGIN_LABEL,
        'version' => ROLLBACK_V2,
        'type' => 'entity',
        'core_version' => ROLLBACK_V1,
    ], false, false, null, $lifecycle);

    try {
        $GLOBALS['rollback_to_version'] = null;
        $service = buildPluginRollbackService($root, $pdo);
        $result = $service->rollback($slug);

        assertEquals(ROLLBACK_V2, $result['rollback']['from_version'], 'Rollback should report current version as from');
        assertEquals(ROLLBACK_V1, $result['rollback']['to_version'], 'Rollback should report snapshot version as target');
        assertEquals(ROLLBACK_V1, $GLOBALS['rollback_to_version'] ?? null, 'onRollback should receive target version');

        $stmt = $pdo->prepare("SELECT manifest_json->>'version' AS version, status, schema_json FROM plugins WHERE slug = :slug");
        $stmt->execute([ROLLBACK_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(ROLLBACK_V1, (string) ($row['version'] ?? ''), 'Version should be restored from snapshot');
        assertEquals('active', (string) ($row['status'] ?? ''), 'Status should be restored from snapshot');
        assertTrue(!isset($decoded['fields']['email']), 'schema_json should be restored from snapshot');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('rollback() fails when no snapshot matches installed version', function () use ($pdo): void {
    $slug = 'test_rollback_missing_' . bin2hex(random_bytes(3));

    insertRollbackTestPlugin($pdo, $slug, ['label' => 'Rollback Missing', 'version' => ROLLBACK_V2], 'inactive', [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]);

    try {
        $service = buildPluginRollbackService(sys_get_temp_dir(), $pdo);
        $threw = false;
        try {
            $service->rollback($slug);
        } catch (DomainException) {
            $threw = true;
        }

        assertTrue($threw, 'Rollback should fail when there is no snapshot for the installed version');

        $stmt = $pdo->prepare("SELECT manifest_json->>'version' AS version FROM plugins WHERE slug = :slug");
        $stmt->execute([ROLLBACK_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertEquals(ROLLBACK_V2, (string) ($row['version'] ?? ''), 'Version must remain unchanged without snapshot');
    } finally {
        cleanupPluginRecord($pdo, $slug);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
