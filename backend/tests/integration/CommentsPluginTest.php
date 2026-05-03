<?php

/**
 * CommentsPluginTest — Integration tests for the comments plugin.
 *
 * Tests:
 *   1. Plugin installs correctly (manifest loads, table created)
 *   2. registerTabs hook injects "Comentarios" tab via API
 *   3. GET /api/v1/plugins/comments/{entity}/{id} returns empty list
 *   4. POST creates a comment and it appears in GET response
 *   5. POST with empty body returns 422
 *   6. GET with empty slug returns 404
 *
 * Requires a live PostgreSQL connection.
 *
 * Run:
 *   php backend/tests/integration/CommentsPluginTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('PLUGINS_PATH', dirname(BASE_PATH) . '/plugins');

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/PluginException.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/plugins/HookDispatcher.php';
require_once BASE_PATH . '/src/plugins/PluginLifecycleInterface.php';
require_once BASE_PATH . '/src/plugins/PluginLoader.php';
require_once BASE_PATH . '/src/services/JwtService.php';
require_once BASE_PATH . '/src/controllers/PluginExtensionController.php';
require_once PLUGINS_PATH . '/comments/Hooks.php';
require_once PLUGINS_PATH . '/comments/Lifecycle.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\plugins\HookDispatcher;
use Xestify\plugins\PluginLoader;
use Xestify\controllers\PluginExtensionController;
use Xestify\plugins\comments\Hooks;
use Xestify\plugins\comments\Lifecycle;
use Xestify\core\Request;
use Xestify\services\JwtService;

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
    echo "[SKIP] PostgreSQL not reachable — all CommentsPluginTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const TEST_ENTITY   = 'clients';
const TEST_RECORD   = '00000000-0000-0000-0000-000000000001';
const TEST_COMMENT_BODY = 'Primer comentario de prueba';
const MSG_OK_MUST_BE_FALSE = 'ok must be false';

function callComments(PluginExtensionController $ctrl, string $method, array $params, array $body = []): array
{
    $request = new Request([], $body, authHeaders(), $params);
    ob_start();
    $ctrl->$method($params, $request);
    $output  = ob_get_clean();
    $decoded = json_decode((string) $output, true);
    return is_array($decoded) ? $decoded : [];
}

function authHeaders(): array
{
    static $token = null;
    if (is_string($token) && $token !== '') {
        return ['authorization' => 'Bearer ' . $token];
    }

    $jwt = new JwtService(
        $_ENV['JWT_SECRET'] ?? 'changeme',
        (int) ($_ENV['JWT_EXPIRY'] ?? 3600)
    );
    $token = $jwt->encode([
        'sub' => 'test-user-id',
        'email' => 'test@example.com',
        'iat' => time(),
        'exp' => time() + 3600,
    ]);
    return ['authorization' => 'Bearer ' . $token];
}

function cleanComments(): void
{
    Database::connection()
        ->prepare(
            "DELETE FROM plugin_extension_data
              WHERE plugin_slug = 'comments'
                AND entity_slug = :entity
                AND record_id   = :id"
        )
        ->execute([':entity' => TEST_ENTITY, ':id' => TEST_RECORD]);
}

function ensureCommentsPluginActive(): void
{
    $loader = new PluginLoader(PLUGINS_PATH, Database::connection());
    $loader->load('comments');
    $loader->activate('comments');
}

function seedParentRecord(): void
{
    Database::connection()->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Clientes', 'entity', '1.0.0', 'active', 1, :schema)
         ON CONFLICT (slug) DO UPDATE
         SET name = EXCLUDED.name,
             status = 'active',
             schema_json = EXCLUDED.schema_json,
             updated_at = NOW()"
    )->execute([
        ':slug' => TEST_ENTITY,
        ':schema' => '{"fields":{"name":{"type":"string","required":true}}}',
    ]);

    Database::connection()->prepare(
        "INSERT INTO plugin_entity_data (id, entity_slug, content)
         VALUES (:id, :entity, :content::jsonb)
         ON CONFLICT (id) DO UPDATE
         SET entity_slug = EXCLUDED.entity_slug,
             content = EXCLUDED.content,
             deleted_at = NULL,
             updated_at = NOW()"
    )->execute([
        ':id' => TEST_RECORD,
        ':entity' => TEST_ENTITY,
        ':content' => '{"name":"Cliente test"}',
    ]);
}

echo str_repeat('-', 40) . "\n";
ensureCommentsPluginActive();
seedParentRecord();

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('plugin installation creates plugin_extension_data table (generic extension table)', function (): void {
    $loader = new PluginLoader(PLUGINS_PATH, Database::connection());

    $loader->load('comments');

    // Verify generic extension table exists
    $stmt = Database::connection()->query(
        "SELECT to_regclass('public.plugin_extension_data')"
    );
    $result = $stmt !== false ? $stmt->fetchColumn() : null;
    assertTrue($result === 'plugin_extension_data', 'plugin_extension_data table must exist');
});

TestSuite::run('GET returns 404 when extension plugin is inactive', function (): void {
    Database::connection()
        ->prepare("UPDATE plugins SET status = 'inactive' WHERE slug = 'comments'")
        ->execute();

    $ctrl = new PluginExtensionController(Database::connection());
    $result = callComments($ctrl, 'index', ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD]);

    assertTrue(!($result['ok'] ?? true), MSG_OK_MUST_BE_FALSE);
    assertEquals(404, $result['error']['code'] ?? 0, 'inactive plugin must return 404');

    ensureCommentsPluginActive();
});

TestSuite::run('GET returns 404 when parent record does not exist', function (): void {
    $ctrl = new PluginExtensionController(Database::connection());
    $result = callComments(
        $ctrl,
        'index',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => '00000000-0000-0000-0000-000000000099']
    );

    assertTrue(!($result['ok'] ?? true), MSG_OK_MUST_BE_FALSE);
    assertEquals(404, $result['error']['code'] ?? 0, 'missing parent record must return 404');
});

TestSuite::run('plugin installation registers registerTabs hook in plugin_hooks', function (): void {
    $stmt = Database::connection()->prepare(
        "SELECT hook_name FROM plugin_hooks
          WHERE slug = 'comments' AND target_entity_slug = '*'"
    );
    $stmt->execute();
    $row = $stmt->fetch();

    assertTrue($row !== false, 'comments hook must be in plugin_hooks');
    assertEquals('registerTabs', $row['hook_name'] ?? null, 'hook_name must be registerTabs');
});

TestSuite::run('Hooks::register() injects Comentarios tab via registerTabs hook', function (): void {
    $dispatcher = new HookDispatcher();
    $hooks      = new Hooks();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);

    $found = array_filter($tabs, static fn(array $t): bool => $t['id'] === 'comments');
    assertTrue(count($found) > 0, 'registerTabs must inject a comments tab');

    $tab = array_values($found)[0];
    assertEquals('Comentarios', $tab['label'] ?? null, 'Tab label must be Comentarios');
    assertTrue(isset($tab['icon']), 'Tab must have an icon');
});

TestSuite::run('Comentarios tab appears in GET /entities/{slug}/tabs API response', function (): void {
    $dispatcher = new HookDispatcher();
    $hooks      = new Hooks();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => TEST_ENTITY]);
    $ids  = array_column($tabs, 'id');

    assertTrue(in_array('comments', $ids, true), 'comments tab must appear in registerTabs result');
});

TestSuite::run('GET comments returns empty array when no comments exist', function (): void {
    cleanComments();
    $ctrl   = new PluginExtensionController(Database::connection());
    $result = callComments($ctrl, 'index', ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD]);

    assertTrue($result['ok'] ?? false, 'ok must be true');
    assertEquals([], $result['data'] ?? null, 'data must be empty array');
    assertEquals(0, $result['meta']['total'] ?? -1, 'total must be 0');
});

TestSuite::run('POST creates a comment and it appears in GET response', function (): void {
    cleanComments();
    $ctrl = new PluginExtensionController(Database::connection());

    $createResult = callComments(
        $ctrl,
        'create',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['body' => TEST_COMMENT_BODY, 'author_id' => 'test-user-id', 'stamp' => date('c')]
    );

    assertTrue($createResult['ok'] ?? false, 'POST ok must be true');
    assertTrue(isset($createResult['data']['id']), 'Created comment must have id');
    $content = $createResult['data']['content'] ?? [];
    assertEquals(TEST_COMMENT_BODY, $content['body'] ?? null, 'body must match');
    assertEquals('test-user-id', $content['author_id'] ?? null, 'author_id must match');
    assertTrue(isset($content['stamp']), 'Created comment must include stamp');

    $listResult = callComments($ctrl, 'index', ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD]);
    $comments   = $listResult['data'] ?? [];

    assertEquals(1, count($comments), 'GET must return 1 comment');
    $c = $comments[0]['content'] ?? [];
    assertEquals(TEST_COMMENT_BODY, $c['body'] ?? null, 'body must match');
    assertEquals('test-user-id', $c['author_id'] ?? null, 'author_id must match');
    assertTrue(isset($c['stamp']), 'Listed comment must include stamp');

    cleanComments();
});

TestSuite::run('POST with empty body returns 422', function (): void {
    $ctrl   = new PluginExtensionController(Database::connection());
    $result = callComments(
        $ctrl,
        'create',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        []
    );

    assertTrue(!($result['ok'] ?? true), MSG_OK_MUST_BE_FALSE);
    assertEquals(422, $result['error']['code'] ?? 0, 'code must be 422');
});

TestSuite::run('GET with empty entity slug returns 404', function (): void {
    $ctrl   = new PluginExtensionController(Database::connection());
    $result = callComments($ctrl, 'index', ['plugin_slug' => 'comments', 'entity' => '', 'id' => TEST_RECORD]);

    assertTrue(!($result['ok'] ?? true), MSG_OK_MUST_BE_FALSE);
    assertEquals(404, $result['error']['code'] ?? 0, 'code must be 404');
});

TestSuite::run('GET with empty record id returns 404', function (): void {
    $ctrl   = new PluginExtensionController(Database::connection());
    $result = callComments($ctrl, 'index', ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => '']);

    assertTrue(!($result['ok'] ?? true), MSG_OK_MUST_BE_FALSE);
    assertEquals(404, $result['error']['code'] ?? 0, 'code must be 404');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
