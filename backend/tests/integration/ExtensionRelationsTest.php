<?php

/**
 * ExtensionRelationsTest — Integration tests for STORY 10.5's core changes:
 *   1. PluginExtensionController now validates content against the extension's
 *      own schema (ValidationService), which it never did before — min/max/
 *      required on `fields` are enforced.
 *   2. Extension schemas can declare `relations` (previously entity-only);
 *      their values survive ExtensionPluginContentService::normalizeContentBySchema()
 *      instead of being silently dropped, and pass validation unchecked
 *      (DECISION 6, same rule entity relations already get).
 *
 * Uses a synthetic extension plugin instance (not `optometry`/`contact-lenses`,
 * which don't exist yet) so this suite is independent of those plugins landing.
 *
 * Requires a live PostgreSQL connection.
 *
 * Run:
 *   php backend/tests/integration/ExtensionRelationsTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_services.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/controllers/PluginExtensionController.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\controllers\PluginExtensionController;
use Xestify\core\Request;

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
// DB connectivity probe
// ---------------------------------------------------------------------------

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all ExtensionRelationsTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const TEST_PLUGIN_SLUG = 'ext_relations_test';
const TEST_ENTITY       = 'persons';
const TEST_RECORD       = '00000000-0000-0000-0000-000000000002';
const TARGET_RECORD     = '00000000-0000-0000-0000-000000000003';
const MSG_OK_MUST_BE_FALSE = 'ok must be false';
const MSG_OK_MUST_BE_TRUE  = 'ok must be true';
const TEST_PLUGIN_VERSION  = '1.0.0';
const TEST_EVENT_DATE      = '2026-08-17';

function callExt(PluginExtensionController $ctrl, string $method, array $params, array $body = []): array
{
    $request = new Request([], $body, [], $params);
    ob_start();
    $ctrl->$method($params, $request);
    $output  = ob_get_clean();
    $decoded = json_decode((string) $output, true);
    return is_array($decoded) ? $decoded : [];
}

function seedPersonsParentPlugin(): void
{
    $manifest = json_encode([
        'name' => TEST_ENTITY,
        'label' => 'Personas',
        'label_singular' => 'Persona',
        'version' => TEST_PLUGIN_VERSION,
        'type' => 'entity',
        'core_version' => TEST_PLUGIN_VERSION,
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $schema = json_encode([
        'identities' => ['id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false]],
        'fields' => ['name' => ['type' => 'string', 'required' => true, 'label' => 'Nombre']],
        'custom_fields' => [],
        'relations' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Database::connection()->prepare(
        "INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, 'active', CAST(:manifest AS jsonb), CAST(:schema AS jsonb))
         ON CONFLICT (slug) DO UPDATE
         SET manifest_json = EXCLUDED.manifest_json,
             status = 'active',
             updated_at = NOW()"
    )->execute([':slug' => TEST_ENTITY, ':manifest' => $manifest, ':schema' => $schema]);

    foreach ([TEST_RECORD, TARGET_RECORD] as $id) {
        Database::connection()->prepare(
            "INSERT INTO plugin_entity_data (id, entity_slug, content)
             VALUES (:id, :entity, :content::jsonb)
             ON CONFLICT (id) DO UPDATE
             SET entity_slug = EXCLUDED.entity_slug,
                 content = EXCLUDED.content,
                 deleted_at = NULL,
                 updated_at = NOW()"
        )->execute([':id' => $id, ':entity' => TEST_ENTITY, ':content' => '{"name":"Persona test"}']);
    }
}

function seedExtensionPluginWithRelations(): void
{
    $manifest = json_encode([
        'name' => TEST_PLUGIN_SLUG,
        'label' => 'Extension relations test',
        'version' => TEST_PLUGIN_VERSION,
        'type' => 'extension',
        'core_version' => TEST_PLUGIN_VERSION,
        'target_entity' => TEST_ENTITY,
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $schema = json_encode([
        'fields' => [
            'event_date' => ['type' => 'date', 'required' => true, 'label' => 'Fecha'],
            'score' => ['type' => 'number', 'required' => false, 'label' => 'Score', 'min' => 0, 'max' => 10],
        ],
        'relations' => [
            [
                'key' => 'owner_ref', 'type' => 'belongs_to', 'target_entity' => TEST_ENTITY,
                'target_field' => 'id', 'required' => false, 'label' => 'Owner ref', 'group' => 'general',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Database::connection()->prepare(
        "INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, 'active', CAST(:manifest AS jsonb), CAST(:schema AS jsonb))
         ON CONFLICT (slug) DO UPDATE
         SET manifest_json = EXCLUDED.manifest_json,
             schema_json = EXCLUDED.schema_json,
             status = 'active',
             updated_at = NOW()"
    )->execute([':slug' => TEST_PLUGIN_SLUG, ':manifest' => $manifest, ':schema' => $schema]);
}

function cleanExtensionData(): void
{
    Database::connection()
        ->prepare("DELETE FROM plugin_extension_data WHERE plugin_slug = :plugin AND entity_slug = :entity AND record_id = :id")
        ->execute([':plugin' => TEST_PLUGIN_SLUG, ':entity' => TEST_ENTITY, ':id' => TEST_RECORD]);
}

echo str_repeat('-', 40) . "\n";
seedPersonsParentPlugin();
seedExtensionPluginWithRelations();

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('POST rejects a number above its schema max (validation now runs for extensions)', function (): void {
    cleanExtensionData();
    $ctrl = new PluginExtensionController(Database::connection());

    $result = callExt(
        $ctrl,
        'create',
        ['plugin_slug' => TEST_PLUGIN_SLUG, 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['event_date' => TEST_EVENT_DATE, 'score' => 999]
    );

    assertTrue(!($result['ok'] ?? true), MSG_OK_MUST_BE_FALSE);
    assertEquals(422, $result['error']['code'] ?? 0, 'out-of-range number must return 422');
    $errors = $result['error']['details'] ?? [];
    $codes = array_column($errors, 'code');
    assertTrue(in_array('max_value', $codes, true), 'error must report max_value');
});

TestSuite::run('POST rejects a missing required field (required now enforced for extensions)', function (): void {
    cleanExtensionData();
    $ctrl = new PluginExtensionController(Database::connection());

    $result = callExt(
        $ctrl,
        'create',
        ['plugin_slug' => TEST_PLUGIN_SLUG, 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['score' => 5]
    );

    assertTrue(!($result['ok'] ?? true), MSG_OK_MUST_BE_FALSE);
    assertEquals(422, $result['error']['code'] ?? 0, 'missing required field must return 422');
    $errors = $result['error']['details'] ?? [];
    $codes = array_column($errors, 'code');
    assertTrue(in_array('required', $codes, true), 'error must report required');
});

TestSuite::run('POST with a valid payload including a relation value persists the relation key', function (): void {
    cleanExtensionData();
    $ctrl = new PluginExtensionController(Database::connection());

    $result = callExt(
        $ctrl,
        'create',
        ['plugin_slug' => TEST_PLUGIN_SLUG, 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['event_date' => TEST_EVENT_DATE, 'score' => 7, 'owner_ref' => TARGET_RECORD]
    );

    assertTrue($result['ok'] ?? false, MSG_OK_MUST_BE_TRUE);
    $content = $result['data']['content'] ?? [];
    assertEquals(TEST_EVENT_DATE, $content['event_date'] ?? null, 'event_date must persist');
    assertEquals(7, $content['score'] ?? null, 'score must persist');
    assertEquals(
        TARGET_RECORD,
        $content['owner_ref'] ?? null,
        'owner_ref (a relations[] key, not fields[]) must persist instead of being silently dropped'
    );

    cleanExtensionData();
});

TestSuite::run('PUT partial update touching only the relation key succeeds and updates it', function (): void {
    cleanExtensionData();
    $ctrl = new PluginExtensionController(Database::connection());

    $created = callExt(
        $ctrl,
        'create',
        ['plugin_slug' => TEST_PLUGIN_SLUG, 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['event_date' => TEST_EVENT_DATE]
    );
    $itemId = (string) ($created['data']['id'] ?? '');
    assertTrue($itemId !== '', 'created item must have an id');

    $updated = callExt(
        $ctrl,
        'update',
        ['plugin_slug' => TEST_PLUGIN_SLUG, 'entity' => TEST_ENTITY, 'id' => TEST_RECORD, 'item_id' => $itemId],
        ['owner_ref' => TARGET_RECORD]
    );

    assertTrue($updated['ok'] ?? false, 'partial update touching only the relation must succeed (requireAll=false)');
    $content = $updated['data']['content'] ?? [];
    assertEquals(TARGET_RECORD, $content['owner_ref'] ?? null, 'owner_ref must be updated');

    cleanExtensionData();
});

// ---------------------------------------------------------------------------
Database::connection()->prepare('DELETE FROM plugins WHERE slug IN (:a, :b)')
    ->execute([':a' => TEST_PLUGIN_SLUG, ':b' => TEST_ENTITY]);

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
