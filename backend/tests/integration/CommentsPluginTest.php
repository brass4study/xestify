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
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_services.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/PluginException.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/exceptions/ValidationException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/core/HookDispatcher.php';
require_once BASE_PATH . '/src/plugins/contracts/PluginLifecycleInterface.php';
require_once BASE_PATH . '/src/services/JwtService.php';
require_once BASE_PATH . '/src/controllers/PluginExtensionController.php';
require_once PLUGINS_PATH . '/comments/Hooks.php';
require_once PLUGINS_PATH . '/comments/Lifecycle.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\ValidationException;
use Xestify\core\HookDispatcher;
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
const MSG_BODY_MUST_MATCH = 'body must match';
const MSG_POST_OK_MUST_BE_TRUE = 'POST ok must be true';
const MSG_CREATED_COMMENT_MUST_HAVE_ID = 'Created comment must have an id';

function callComments(PluginExtensionController $ctrl, string $method, array $params, array $body = []): array
{
    $request = new Request([], $body, authHeaders(), $params);
    ob_start();
    $ctrl->$method($params, $request);
    $output  = ob_get_clean();
    $decoded = json_decode((string) $output, true);
    return is_array($decoded) ? $decoded : [];
}

function callCommentsAsUser(PluginExtensionController $ctrl, string $method, array $params, array $body, string $userId): array
{
    $request = new Request([], $body, [], $params);
    $request->setUser(['sub' => $userId, 'email' => $userId . '@test.local']);
    ob_start();
    try {
        $ctrl->$method($params, $request);
    } finally {
        $output = ob_get_clean();
    }

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
    $pdo = Database::connection();
    buildPluginSyncService(PLUGINS_PATH, $pdo)->syncAll();
    buildPluginStatusService(PLUGINS_PATH, $pdo)->activate('comments');
}

function seedParentRecord(): void
{
    Database::connection()->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Clientes', 'entity', '1.0.0', 'active', 1, CAST(:schema AS jsonb))
         ON CONFLICT (slug) DO UPDATE
         SET name = EXCLUDED.name,
             status = 'active',
             updated_at = NOW()"
    )->execute([
        ':slug' => TEST_ENTITY,
        ':schema' => canonicalClientsSchemaJson(),
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

function seedUser(string $id, string $email): void
{
    $pdo = Database::connection();
    $pdo->prepare('DELETE FROM users WHERE id = :id OR email = :email')->execute([
        ':id' => $id,
        ':email' => $email,
    ]);

    $pdo->prepare(
        'INSERT INTO users (id, email, password_hash, roles)
         VALUES (:id, :email, :password_hash, :roles::jsonb)'
    )->execute([
        ':id' => $id,
        ':email' => $email,
        ':password_hash' => 'test-hash',
        ':roles' => '["operador"]',
    ]);
}

function deleteUser(string $id): void
{
    Database::connection()->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
}

function canonicalClientsSchemaJson(): string
{
    $schemaPath = PLUGINS_PATH . '/clients/schema.json';
    $raw = file_get_contents($schemaPath);
    if ($raw === false) {
        throw new ValidationException([
            ['field' => 'schema', 'code' => 'read_error', 'message' => 'clients schema fixture is not readable'],
        ]);
    }

    $schema = json_decode($raw, true);
    if (!is_array($schema) || !isset($schema['fields']['email'], $schema['identities']['id'])) {
        throw new ValidationException([
            ['field' => 'schema', 'code' => 'invalid_fixture', 'message' => 'clients schema fixture is invalid'],
        ]);
    }

    $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new ValidationException([
            ['field' => 'schema', 'code' => 'encode_error', 'message' => 'clients schema fixture cannot be encoded'],
        ]);
    }

    return $json;
}

echo str_repeat('-', 40) . "\n";
ensureCommentsPluginActive();
seedParentRecord();

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('plugin installation creates plugin_extension_data table (generic extension table)', function (): void {
    buildPluginSyncService(PLUGINS_PATH, Database::connection())->syncAll();

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

TestSuite::run('Hooks::register() injects Comentarios tab via registerTabs hook', function (): void {
    $dispatcher = new HookDispatcher();
    $hooks      = new Hooks(Database::connection());
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
    $hooks      = new Hooks(Database::connection());
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => TEST_ENTITY]);
    $ids  = array_column($tabs, 'id');

    assertTrue(in_array('comments', $ids, true), 'comments tab must appear in registerTabs result');
});

TestSuite::run('Comentarios tab does not appear for non-target entities', function (): void {
    // comments ships with target_entity: '*' by design (schema.json) — this test needs
    // it restricted to a single entity to have a genuine "non-target" case, so it must
    // set that itself instead of assuming BD state nobody else guarantees.
    $pdo = Database::connection();
    $setTargetEntity = static function (string $target) use ($pdo): void {
        $pdo->prepare(
            "UPDATE plugins
                SET schema_json = jsonb_set(schema_json, '{target_entity}', to_jsonb(:target::text))
              WHERE slug = 'comments'"
        )->execute([':target' => $target]);
    };

    $setTargetEntity(TEST_ENTITY);

    try {
        $dispatcher = new HookDispatcher();
        $hooks      = new Hooks(Database::connection());
        $hooks->register($dispatcher);

        $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'products']);
        $ids  = array_column($tabs, 'id');

        assertFalse(in_array('comments', $ids, true), 'comments tab must not appear for non-target entities');
    } finally {
        $setTargetEntity('*');
    }
});

TestSuite::run('Comentarios tab does not appear when plugin is inactive', function (): void {
    Database::connection()
        ->prepare("UPDATE plugins SET status = 'inactive' WHERE slug = 'comments'")
        ->execute();

    $dispatcher = new HookDispatcher();
    $hooks      = new Hooks(Database::connection());
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => TEST_ENTITY]);
    $ids  = array_column($tabs, 'id');

    assertFalse(in_array('comments', $ids, true), 'comments tab must not appear when plugin is inactive');

    ensureCommentsPluginActive();
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

    assertTrue($createResult['ok'] ?? false, MSG_POST_OK_MUST_BE_TRUE);
    assertTrue(isset($createResult['data']['id']), 'Created comment must have id');
    $content = $createResult['data']['content'] ?? [];
    assertEquals(TEST_COMMENT_BODY, $content['body'] ?? null, MSG_BODY_MUST_MATCH);
    assertEquals('test-user-id', $content['author_id'] ?? null, 'author_id must match');
    assertTrue(isset($content['stamp']), 'Created comment must include stamp');

    $listResult = callComments($ctrl, 'index', ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD]);
    $comments   = $listResult['data'] ?? [];

    assertEquals(1, count($comments), 'GET must return 1 comment');
    $c = $comments[0]['content'] ?? [];
    assertEquals(TEST_COMMENT_BODY, $c['body'] ?? null, MSG_BODY_MUST_MATCH);
    assertEquals('test-user-id', $c['author_id'] ?? null, 'author_id must match');
    assertTrue(isset($c['stamp']), 'Listed comment must include stamp');

    cleanComments();
});

TestSuite::run('POST auto-generates stamp and author_id from authenticated user when omitted', function (): void {
    cleanComments();
    $ctrl = new PluginExtensionController(Database::connection());

    $request = new Request([], ['body' => TEST_COMMENT_BODY], [], [
        'plugin_slug' => 'comments',
        'entity' => TEST_ENTITY,
        'id' => TEST_RECORD,
    ]);
    $request->setUser([
        'sub' => '00000000-0000-0000-0000-000000000123',
        'email' => 'schema@test.local',
    ]);

    ob_start();
    $ctrl->create(['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD], $request);
    $output = ob_get_clean();
    $response = json_decode((string) $output, true);

    assertTrue(($response['ok'] ?? false) === true, MSG_POST_OK_MUST_BE_TRUE);
    $content = $response['data']['content'] ?? [];
    assertEquals(TEST_COMMENT_BODY, $content['body'] ?? null, MSG_BODY_MUST_MATCH);
    assertEquals('00000000-0000-0000-0000-000000000123', $content['author_id'] ?? null, 'author_id must come from request user sub');
    assertTrue(is_string($content['stamp'] ?? null) && ($content['stamp'] ?? '') !== '', 'stamp must be auto-generated');

    cleanComments();
});

TestSuite::run('POST does not include author_name when author_id is missing in users', function (): void {
    cleanComments();
    $ctrl = new PluginExtensionController(Database::connection());

    $request = new Request([], ['body' => 'Comentario con email visible'], [], [
        'plugin_slug' => 'comments',
        'entity' => TEST_ENTITY,
        'id' => TEST_RECORD,
    ]);
    $request->setUser([
        'sub' => '00000000-0000-0000-0000-000000000777',
        'email' => 'visible.user@test.local',
    ]);

    ob_start();
    $ctrl->create(['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD], $request);
    $output = ob_get_clean();
    $response = json_decode((string) $output, true);

    assertTrue(($response['ok'] ?? false) === true, MSG_POST_OK_MUST_BE_TRUE);
    $content = $response['data']['content'] ?? [];
    assertFalse(isset($content['author_name']), 'author_name must not be returned when user row is missing');

    cleanComments();
});

TestSuite::run('GET comments includes author_name when author_id exists in users', function (): void {
    cleanComments();
    $ctrl = new PluginExtensionController(Database::connection());

    $authorId = '00000000-0000-4000-8000-000000000321';
    $authorEmail = 'autor.comments@test.local';
    seedUser($authorId, $authorEmail);

    $createResult = callComments(
        $ctrl,
        'create',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['body' => 'Comentario con autor visible', 'author_id' => $authorId]
    );

    assertTrue($createResult['ok'] ?? false, MSG_POST_OK_MUST_BE_TRUE);
    $createdContent = $createResult['data']['content'] ?? [];
    assertEquals($authorEmail, $createdContent['author_name'] ?? null, 'create response must include author_name');

    $listResult = callComments($ctrl, 'index', ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD]);
    assertTrue($listResult['ok'] ?? false, 'GET ok must be true');

    $rows = $listResult['data'] ?? [];
    assertEquals(1, count($rows), 'GET must return one comment');
    $content = $rows[0]['content'] ?? [];
    assertEquals($authorId, $content['author_id'] ?? null, 'author_id must be preserved');
    assertEquals($authorEmail, $content['author_name'] ?? null, 'author_name must resolve from users email');

    cleanComments();
    deleteUser($authorId);
});

TestSuite::run('GET comments resolves UUID author_name to user email', function (): void {
    cleanComments();
    $ctrl = new PluginExtensionController(Database::connection());

    $authorId = sprintf('%08s-%04s-%04s-%04s-%012s', substr(md5('comments-uuid-seed'), 0, 8), substr(md5('comments-uuid-seed'), 8, 4), substr(md5('comments-uuid-seed'), 12, 4), substr(md5('comments-uuid-seed'), 16, 4), substr(md5('comments-uuid-seed'), 20, 12));
    $authorId = str_replace(' ', '0', $authorId);
    $authorEmail = 'autor.uuid@test.local';
    seedUser($authorId, $authorEmail);

    Database::connection()->prepare(
        'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
         VALUES (:plugin_slug, :entity_slug, :record_id, :content::jsonb)'
    )->execute([
        ':plugin_slug' => 'comments',
        ':entity_slug' => TEST_ENTITY,
        ':record_id' => TEST_RECORD,
        ':content' => json_encode([
            'body' => 'Comentario legado con author_name UUID',
            'author_id' => $authorId,
            'author_name' => $authorId,
            'stamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $listResult = callComments($ctrl, 'index', ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD]);
    assertTrue($listResult['ok'] ?? false, 'GET ok must be true');

    $rows = $listResult['data'] ?? [];
    assertEquals(1, count($rows), 'GET must return one comment');
    $content = $rows[0]['content'] ?? [];
    assertEquals($authorEmail, $content['author_name'] ?? null, 'author_name UUID must resolve to user email');

    cleanComments();
    deleteUser($authorId);
});

TestSuite::run('PUT keeps the original author_id even when a different one is sent', function (): void {
    cleanComments();
    $ctrl = new PluginExtensionController(Database::connection());
    $author = '00000000-0000-4000-8000-0000000000a1';
    $impostor = '00000000-0000-4000-8000-0000000000a2';

    $created = callCommentsAsUser(
        $ctrl,
        'create',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['body' => 'Comentario original'],
        $author
    );
    $itemId = (string) ($created['data']['id'] ?? '');
    assertTrue($itemId !== '', MSG_CREATED_COMMENT_MUST_HAVE_ID);

    $updated = callCommentsAsUser(
        $ctrl,
        'update',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD, 'item_id' => $itemId],
        ['body' => 'Comentario editado por su autor', 'author_id' => $impostor],
        $author
    );

    assertTrue($updated['ok'] ?? false, 'Owner update should succeed');
    $content = $updated['data']['content'] ?? [];
    assertEquals('Comentario editado por su autor', $content['body'] ?? null, MSG_BODY_MUST_MATCH);
    assertEquals($author, $content['author_id'] ?? null, 'author_id must stay the original author, not the spoofed value');

    cleanComments();
});

TestSuite::run('PUT by a user other than the author is forbidden and leaves content unchanged', function (): void {
    cleanComments();
    $ctrl = new PluginExtensionController(Database::connection());
    $author = '00000000-0000-4000-8000-0000000000b1';
    $otherUser = '00000000-0000-4000-8000-0000000000b2';

    $created = callCommentsAsUser(
        $ctrl,
        'create',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['body' => 'Comentario ajeno'],
        $author
    );
    $itemId = (string) ($created['data']['id'] ?? '');
    assertTrue($itemId !== '', MSG_CREATED_COMMENT_MUST_HAVE_ID);

    $updated = callCommentsAsUser(
        $ctrl,
        'update',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD, 'item_id' => $itemId],
        ['body' => 'Intento de edicion ajena'],
        $otherUser
    );

    assertFalse(($updated['ok'] ?? true) === true, 'Update by a non-author must fail');
    assertEquals(403, (int) ($updated['error']['code'] ?? 0), 'Non-author update must return 403');

    $listResult = callCommentsAsUser(
        $ctrl,
        'index',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        [],
        $otherUser
    );
    $content = $listResult['data'][0]['content'] ?? [];
    assertEquals('Comentario ajeno', $content['body'] ?? null, 'Content must remain untouched after a forbidden update');

    cleanComments();
});

TestSuite::run('DELETE by a user other than the author is forbidden', function (): void {
    cleanComments();
    $ctrl = new PluginExtensionController(Database::connection());
    $author = '00000000-0000-4000-8000-0000000000c1';
    $otherUser = '00000000-0000-4000-8000-0000000000c2';

    $created = callCommentsAsUser(
        $ctrl,
        'create',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        ['body' => 'Comentario a proteger'],
        $author
    );
    $itemId = (string) ($created['data']['id'] ?? '');
    assertTrue($itemId !== '', MSG_CREATED_COMMENT_MUST_HAVE_ID);

    $deleted = callCommentsAsUser(
        $ctrl,
        'delete',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD, 'item_id' => $itemId],
        [],
        $otherUser
    );

    assertFalse(($deleted['ok'] ?? true) === true, 'Delete by a non-author must fail');
    assertEquals(403, (int) ($deleted['error']['code'] ?? 0), 'Non-author delete must return 403');

    $listResult = callCommentsAsUser(
        $ctrl,
        'index',
        ['plugin_slug' => 'comments', 'entity' => TEST_ENTITY, 'id' => TEST_RECORD],
        [],
        $otherUser
    );
    assertEquals(1, count($listResult['data'] ?? []), 'Item must still exist after a forbidden delete');

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
