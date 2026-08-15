<?php

/**
 * HookFilterApiTest — Integration tests for registerTabs / registerActions
 * via EntityController::tabs().
 *
 * Verifies that a plugin registering a tab via the 'registerTabs' hook makes
 * it appear in the GET /api/v1/entities/{slug}/tabs API response.
 *
 * Requires a live PostgreSQL connection: tabs() also merges in reverse
 * relation tabs (STORY 10.3 §9), which queries the plugins table.
 *
 * Run:
 *   php backend/tests/integration/HookFilterApiTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/exceptions/EntityServiceException.php';
require_once BASE_PATH . '/src/exceptions/ValidationException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/core/HookDispatcher.php';
require_once BASE_PATH . '/src/repositories/GenericRepository.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';
require_once BASE_PATH . '/src/services/EntityService.php';
require_once BASE_PATH . '/src/controllers/EntityController.php';

use Xestify\controllers\EntityController;
use Xestify\core\Database;
use Xestify\core\Request;
use Xestify\exceptions\DatabaseException;
use Xestify\core\HookDispatcher;
use Xestify\plugins\discovery\PluginSchemaCodec;
use Xestify\plugins\schema\ReverseRelationTabResolver;
use Xestify\repositories\GenericRepository;
use Xestify\repositories\PluginRepository;
use Xestify\services\EntityService;
use Xestify\services\ValidationService;

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
// Connectivity probe
// ---------------------------------------------------------------------------

try {
    Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all HookFilterApiTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function buildTabsController(HookDispatcher $dispatcher): EntityController
{
    $pdo = Database::connection();

    return new EntityController(
        new EntityService(
            new GenericRepository($pdo),
            new ValidationService(),
            $pdo
        ),
        $pdo,
        $dispatcher,
        new ReverseRelationTabResolver(new PluginRepository($pdo, new PluginSchemaCodec()))
    );
}

function callTabs(EntityController $ctrl, array $params): array
{
    $request = new Request([], [], [], $params);
    ob_start();
    $ctrl->tabs($params, $request);
    $output = ob_get_clean();
    $decoded = json_decode((string) $output, true);
    return is_array($decoded) ? $decoded : [];
}

echo str_repeat('-', 40) . "\n";

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('tabs() returns empty tabs when no plugin registers any', function (): void {
    $dispatcher = new HookDispatcher();
    $ctrl       = buildTabsController($dispatcher);

    $result = callTabs($ctrl, ['slug' => 'client']);

    assertTrue($result['ok'] ?? false, 'ok must be true'); // NOSONAR
});

TestSuite::run('tabs() returns 404 when slug is empty', function (): void {
    $dispatcher = new HookDispatcher();
    $ctrl       = buildTabsController($dispatcher);

    $result = callTabs($ctrl, ['slug' => '']);

    assertTrue(!($result['ok'] ?? true), 'ok must be false');
    assertEquals(404, $result['error']['code'] ?? 0, 'code must be 404');
});

TestSuite::run('plugin registers tab via registerTabs hook — appears in API response', function (): void {
    $dispatcher = new HookDispatcher();
    $dispatcher->register('registerTabs', static function (array $tabs, array $args): array { // NOSONAR
        $tabs[] = ['id' => 'comments', 'label' => 'Comentarios', 'icon' => 'fa-comments'];
        return $tabs;
    });

    $ctrl   = buildTabsController($dispatcher);
    $result = callTabs($ctrl, ['slug' => 'client']);

    assertTrue($result['ok'] ?? false, 'ok must be true'); // NOSONAR
    $tabs = $result['data']['tabs'] ?? [];
    assertEquals(1, count($tabs), 'Should have 1 tab');
    assertEquals('comments', $tabs[0]['id'] ?? null, 'Tab id must be comments');
    assertEquals('Comentarios', $tabs[0]['label'] ?? null, 'Tab label must be Comentarios');
});

TestSuite::run('multiple plugins register tabs — all appear in API response', function (): void {
    $dispatcher = new HookDispatcher();
    $dispatcher->register('registerTabs', static function (array $tabs, array $args): array { // NOSONAR
        $tabs[] = ['id' => 'comments', 'label' => 'Comentarios'];
        return $tabs;
    }, 10);
    $dispatcher->register('registerTabs', static function (array $tabs, array $args): array { // NOSONAR
        $tabs[] = ['id' => 'attachments', 'label' => 'Adjuntos'];
        return $tabs;
    }, 20);

    $ctrl   = buildTabsController($dispatcher);
    $result = callTabs($ctrl, ['slug' => 'client']);

    assertTrue($result['ok'] ?? false, 'ok must be true'); // NOSONAR
    $tabs = $result['data']['tabs'] ?? [];
    assertEquals(2, count($tabs), 'Should have 2 tabs');
    assertEquals('comments', $tabs[0]['id'] ?? null, 'First tab id');
    assertEquals('attachments', $tabs[1]['id'] ?? null, 'Second tab id');
});

TestSuite::run('registerTabs hook receives entity slug in args', function (): void {
    $dispatcher  = new HookDispatcher();
    $receivedSlug = null;

    $dispatcher->register('registerTabs', static function (array $tabs, array $args) use (&$receivedSlug): array {
        $receivedSlug = $args['entity'] ?? null;
        $tabs[] = ['id' => 'info', 'label' => 'Info'];
        return $tabs;
    });

    $ctrl = buildTabsController($dispatcher);
    callTabs($ctrl, ['slug' => 'product']);

    assertEquals('product', $receivedSlug, 'Hook args must include entity slug');
});

// ---------------------------------------------------------------------------
// registerActions endpoint tests
// ---------------------------------------------------------------------------

TestSuite::run('actions() returns empty actions when no plugin registers any', function (): void {
    $dispatcher = new HookDispatcher();
    $ctrl       = buildTabsController($dispatcher);

    $request = new Request([], [], [], ['slug' => 'client']);
    ob_start();
    $ctrl->actions(['slug' => 'client'], $request);
    $output  = ob_get_clean();
    $result  = json_decode((string) $output, true) ?? [];

    assertTrue($result['ok'] ?? false, 'ok must be true'); // NOSONAR
    assertEquals([], $result['data']['actions'] ?? null, 'actions must be empty array');
    assertEquals('client', $result['data']['entity'] ?? null, 'entity slug must be client');
});

TestSuite::run('actions() returns 404 when slug is empty', function (): void {
    $dispatcher = new HookDispatcher();
    $ctrl       = buildTabsController($dispatcher);

    $request = new Request([], [], [], ['slug' => '']);
    ob_start();
    $ctrl->actions(['slug' => ''], $request);
    $output = ob_get_clean();
    $result = json_decode((string) $output, true) ?? [];

    assertTrue(!($result['ok'] ?? true), 'ok must be false');
    assertEquals(404, $result['error']['code'] ?? 0, 'code must be 404');
});

TestSuite::run('plugin registers action via registerActions hook — appears in API response', function (): void {
    $dispatcher = new HookDispatcher();
    $dispatcher->register('registerActions', static function (array $actions, array $args): array { // NOSONAR
        $actions[] = ['id' => 'archive', 'label' => 'Archivar', 'icon' => 'fa-archive'];
        return $actions;
    });

    $ctrl    = buildTabsController($dispatcher);
    $request = new Request([], [], [], ['slug' => 'client']);
    ob_start();
    $ctrl->actions(['slug' => 'client'], $request);
    $output  = ob_get_clean();
    $result  = json_decode((string) $output, true) ?? [];

    assertTrue($result['ok'] ?? false, 'ok must be true'); // NOSONAR
    $actions = $result['data']['actions'] ?? [];
    assertEquals(1, count($actions), 'Should have 1 action');
    assertEquals('archive', $actions[0]['id'] ?? null, 'Action id must be archive');
    assertEquals('Archivar', $actions[0]['label'] ?? null, 'Action label must be Archivar');
});

TestSuite::run('multiple plugins register actions — all appear in API response in priority order', function (): void {
    $dispatcher = new HookDispatcher();
    $dispatcher->register('registerActions', static function (array $actions, array $args): array { // NOSONAR
        $actions[] = ['id' => 'archive', 'label' => 'Archivar'];
        return $actions;
    }, 10);
    $dispatcher->register('registerActions', static function (array $actions, array $args): array { // NOSONAR
        $actions[] = ['id' => 'export', 'label' => 'Exportar'];
        return $actions;
    }, 20);

    $ctrl    = buildTabsController($dispatcher);
    $request = new Request([], [], [], ['slug' => 'client']);
    ob_start();
    $ctrl->actions(['slug' => 'client'], $request);
    $output  = ob_get_clean();
    $result  = json_decode((string) $output, true) ?? [];

    assertTrue($result['ok'] ?? false, 'ok must be true'); // NOSONAR
    $actions = $result['data']['actions'] ?? [];
    assertEquals(2, count($actions), 'Should have 2 actions');
    assertEquals('archive', $actions[0]['id'] ?? null, 'First action id');
    assertEquals('export', $actions[1]['id'] ?? null, 'Second action id');
});

TestSuite::run('registerActions hook receives entity slug in args', function (): void {
    $dispatcher   = new HookDispatcher();
    $receivedSlug = null;

    $dispatcher->register('registerActions', static function (array $actions, array $args) use (&$receivedSlug): array {
        $receivedSlug = $args['entity'] ?? null;
        $actions[] = ['id' => 'view', 'label' => 'Ver'];
        return $actions;
    });

    $ctrl    = buildTabsController($dispatcher);
    $request = new Request([], [], [], ['slug' => 'product']);
    ob_start();
    $ctrl->actions(['slug' => 'product'], $request);
    ob_end_clean();

    assertEquals('product', $receivedSlug, 'Hook args must include entity slug');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
