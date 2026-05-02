<?php

/**
 * HookFilterApiTest — Integration tests for registerTabs / registerActions
 * via EntityController::tabs().
 *
 * Verifies that a plugin registering a tab via the 'registerTabs' hook makes
 * it appear in the GET /api/v1/entities/{slug}/tabs API response.
 *
 * Does NOT require a live PostgreSQL connection (no DB calls in tabs()).
 *
 * Run:
 *   php backend/tests/integration/HookFilterApiTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/exceptions/EntityServiceException.php';
require_once BASE_PATH . '/src/exceptions/ValidationException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/plugins/HookDispatcher.php';
require_once BASE_PATH . '/src/repositories/GenericRepository.php';
require_once BASE_PATH . '/src/services/ValidationService.php';
require_once BASE_PATH . '/src/services/EntityService.php';
require_once BASE_PATH . '/src/controllers/EntityController.php';

use Xestify\controllers\EntityController;
use Xestify\core\Database;
use Xestify\core\Request;
use Xestify\exceptions\DatabaseException;
use Xestify\plugins\HookDispatcher;
use Xestify\repositories\GenericRepository;
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
// DB connectivity probe (optional — tabs() doesn't need DB)
// ---------------------------------------------------------------------------

$dbAvailable = false;
try {
    Database::connection();
    $dbAvailable = true;
} catch (DatabaseException) {
    // tabs() endpoint does not query the DB, so tests proceed anyway
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function buildTabsController(HookDispatcher $dispatcher): EntityController
{
    global $dbAvailable;
    if ($dbAvailable) {
        $pdo = Database::connection();
        return new EntityController(
            new EntityService(
                new GenericRepository($pdo),
                new ValidationService(),
                $pdo
            ),
            $pdo,
            $dispatcher
        );
    }

    // Minimal stub PDO (tabs() doesn't call DB)
    $pdo = new class extends \PDO {
        public function __construct() {}
    };

    return new EntityController(
        new EntityService(
            new GenericRepository($pdo),
            new ValidationService(),
            $pdo
        ),
        $pdo,
        $dispatcher
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

    assertTrue($result['ok'] ?? false, 'ok must be true');
    assertEquals([], $result['data']['tabs'] ?? null, 'tabs must be empty array');
    assertEquals('client', $result['data']['entity'] ?? null, 'entity slug must be client');
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
    $dispatcher->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'comments', 'label' => 'Comentarios', 'icon' => 'fa-comments'];
        return $tabs;
    });

    $ctrl   = buildTabsController($dispatcher);
    $result = callTabs($ctrl, ['slug' => 'client']);

    assertTrue($result['ok'] ?? false, 'ok must be true');
    $tabs = $result['data']['tabs'] ?? [];
    assertEquals(1, count($tabs), 'Should have 1 tab');
    assertEquals('comments', $tabs[0]['id'] ?? null, 'Tab id must be comments');
    assertEquals('Comentarios', $tabs[0]['label'] ?? null, 'Tab label must be Comentarios');
});

TestSuite::run('multiple plugins register tabs — all appear in API response', function (): void {
    $dispatcher = new HookDispatcher();
    $dispatcher->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'comments', 'label' => 'Comentarios'];
        return $tabs;
    }, 10);
    $dispatcher->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'attachments', 'label' => 'Adjuntos'];
        return $tabs;
    }, 20);

    $ctrl   = buildTabsController($dispatcher);
    $result = callTabs($ctrl, ['slug' => 'client']);

    assertTrue($result['ok'] ?? false, 'ok must be true');
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

    assertTrue($result['ok'] ?? false, 'ok must be true');
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
    $dispatcher->register('registerActions', static function (array $actions, array $args): array {
        $actions[] = ['id' => 'archive', 'label' => 'Archivar', 'icon' => 'fa-archive'];
        return $actions;
    });

    $ctrl    = buildTabsController($dispatcher);
    $request = new Request([], [], [], ['slug' => 'client']);
    ob_start();
    $ctrl->actions(['slug' => 'client'], $request);
    $output  = ob_get_clean();
    $result  = json_decode((string) $output, true) ?? [];

    assertTrue($result['ok'] ?? false, 'ok must be true');
    $actions = $result['data']['actions'] ?? [];
    assertEquals(1, count($actions), 'Should have 1 action');
    assertEquals('archive', $actions[0]['id'] ?? null, 'Action id must be archive');
    assertEquals('Archivar', $actions[0]['label'] ?? null, 'Action label must be Archivar');
});

TestSuite::run('multiple plugins register actions — all appear in API response in priority order', function (): void {
    $dispatcher = new HookDispatcher();
    $dispatcher->register('registerActions', static function (array $actions, array $args): array {
        $actions[] = ['id' => 'archive', 'label' => 'Archivar'];
        return $actions;
    }, 10);
    $dispatcher->register('registerActions', static function (array $actions, array $args): array {
        $actions[] = ['id' => 'export', 'label' => 'Exportar'];
        return $actions;
    }, 20);

    $ctrl    = buildTabsController($dispatcher);
    $request = new Request([], [], [], ['slug' => 'client']);
    ob_start();
    $ctrl->actions(['slug' => 'client'], $request);
    $output  = ob_get_clean();
    $result  = json_decode((string) $output, true) ?? [];

    assertTrue($result['ok'] ?? false, 'ok must be true');
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
