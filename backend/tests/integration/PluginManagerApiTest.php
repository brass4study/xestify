<?php

declare(strict_types=1);

/**
 * PluginManagerApiTest — Integration tests for plugin management endpoints.
 *
 * - GET /api/v1/plugins: list all installed plugins
 * - PUT /api/v1/plugins/{slug}/status: activate/deactivate plugin
 *
 * STORY 6.5
 *
 * Run:
 *   php backend/tests/integration/PluginManagerApiTest.php
 */

define('BASE_PATH', dirname(__DIR__, 2));
define('SEMVER_1_0', '1.0.0');

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/controllers/PluginManagerController.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/plugins/PluginLoader.php';

use Xestify\controllers\PluginManagerController;
use Xestify\core\Request;
use Xestify\plugins\PluginLoader;

// ---------------------------------------------------------------------------
// Mock PDO and Statement classes
// ---------------------------------------------------------------------------

class TestPdo extends \PDO
{
    /** @var array<int, array<string, mixed>> */
    private array $plugins = [
        [
            'slug' => 'clients',
            'name' => 'Clients',
            'plugin_type' => 'entity',
            'version' => SEMVER_1_0,
            'status' => 'active',
            'schema_version' => 1,
            'installed_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ],
        [
            'slug' => 'comments',
            'name' => 'Comments',
            'plugin_type' => 'extension',
            'version' => SEMVER_1_0,
            'status' => 'inactive',
            'schema_version' => 1,
            'installed_at' => '2026-01-02T00:00:00+00:00',
            'updated_at' => '2026-01-02T00:00:00+00:00',
        ],
    ];

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new TestStatement($this->plugins);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        return new TestStatement($this->plugins);
    }

    public function __construct()
    {
        /* stub - no real database connection */
    }
}

class TestStatement extends \PDOStatement
{
    /** @var array<int, array<string, mixed>> */
    private array $plugins;

    /** @var array<string, mixed> */
    private array $lastParams = [];

    public function __construct(array &$plugins)
    {
        $this->plugins = &$plugins;
    }

    public function execute(?array $params = null): bool
    {
        $this->lastParams = $params ?? [];

        if (isset($this->lastParams[':status'], $this->lastParams[':slug'])) {
            $slug = $this->lastParams[':slug'];
            foreach ($this->plugins as &$plugin) {
                if ($plugin['slug'] === $slug) {
                    $plugin['status'] = $this->lastParams[':status'];
                    $plugin['updated_at'] = date('Y-m-d\TH:i:sP');
                    break;
                }
            }
            unset($plugin);
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->plugins;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        if (isset($this->lastParams[':slug'])) {
            $slug = $this->lastParams[':slug'];
            foreach ($this->plugins as $plugin) {
                if ($plugin['slug'] === $slug) {
                    return $plugin;
                }
            }
        }

        return false;
    }
}

class TestRequest extends Request
{
    public function __construct($bodyContent = '')
    {
        parent::__construct(
            query: [],
            body: json_decode($bodyContent, true) ?? [],
            headers: [],
            routeParams: []
        );
    }
}

// ---------------------------------------------------------------------------
// Helper: run test and capture output cleanly
// ---------------------------------------------------------------------------

function testController(callable $fn): string
{
    ob_start();
    $fn();
    return ob_get_clean();
}

function createPluginManagerFixture(array $manifest): string
{
    $root = sys_get_temp_dir() . '/xestify_plugin_manager_test_' . bin2hex(random_bytes(4));
    $slug = (string) ($manifest['slug'] ?? 'test_plugin');
    $pluginDir = $root . '/' . $slug;

    mkdir($pluginDir, 0777, true);
    file_put_contents($pluginDir . '/manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    return $root;
}

function removePluginManagerFixture(string $root): void
{
    if (!is_dir($root)) {
        return;
    }

    $items = scandir($root) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $root . '/' . $item;
        if (is_dir($path)) {
            removePluginManagerFixture($path);
        } else {
            unlink($path);
        }
    }

    rmdir($root);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('GET /api/v1/plugins returns list with all plugins', function () {
    $output = testController(function () {
        $controller = new PluginManagerController(new TestPdo());
        $request = new TestRequest('');
        $request->setUser(['roles' => ['admin']]);
        $controller->listPlugins([], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Response ok should be true');
    assertTrue(isset($response['data']['plugins']), 'Should have plugins array');
    assertEquals(2, count($response['data']['plugins']), 'Should return 2 plugins');
});

TestSuite::run('GET /api/v1/plugins returns plugins with required fields', function () {
    $output = testController(function () {
        $controller = new PluginManagerController(new TestPdo());
        $request = new TestRequest('');
        $request->setUser(['roles' => ['admin']]);
        $controller->listPlugins([], $request);
    });
    
    $response = json_decode($output, true);
    $plugin = $response['data']['plugins'][0];
    
    $fields = [
        'slug',
        'name',
        'plugin_type',
        'version',
        'status',
        'schema_version',
        'installed_at',
        'updated_at',
    ];
    foreach ($fields as $field) {
        assertTrue(isset($plugin[$field]), "Should have $field field");
    }
});

TestSuite::run('GET /api/v1/plugins returns plugins ordered by slug', function () {
    $output = testController(function () {
        $controller = new PluginManagerController(new TestPdo());
        $request = new TestRequest('');
        $request->setUser(['roles' => ['admin']]);
        $controller->listPlugins([], $request);
    });
    
    $response = json_decode($output, true);
    assertEquals($response['data']['plugins'][0]['slug'], 'clients', 'First plugin is clients');
    assertEquals($response['data']['plugins'][1]['slug'], 'comments', 'Second plugin is comments');
});

TestSuite::run('PUT /api/v1/plugins/{slug}/status requires status parameter', function () {
    $output = testController(function () {
        $controller = new PluginManagerController(new TestPdo());
        $request = new TestRequest('{}');
        $request->setUser(['roles' => ['admin']]);
        $controller->updatePluginStatus(['slug' => 'clients'], $request);
    });
    
    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Should fail without status');
    assertEquals(422, $response['error']['code'], 'Error code should be 422');
});

TestSuite::run('PUT /api/v1/plugins/{slug}/status validates status value', function () {
    $output = testController(function () {
        $controller = new PluginManagerController(new TestPdo());
        $request = new TestRequest('{"status":"invalid"}');
        $request->setUser(['roles' => ['admin']]);
        $controller->updatePluginStatus(['slug' => 'clients'], $request);
    });
    
    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Should fail with invalid status');
    assertTrue(
        str_contains($response['error']['message'], 'active')
            || str_contains($response['error']['message'], 'inactive'),
        'Error should mention valid status values'
    );
});

TestSuite::run('PUT /api/v1/plugins/{slug}/status activates plugin', function () {
    $output = testController(function () {
        $controller = new PluginManagerController(new TestPdo());
        $request = new TestRequest('{"status":"active"}');
        $request->setUser(['roles' => ['admin']]);
        $controller->updatePluginStatus(['slug' => 'comments'], $request);
    });
    
    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Should succeed');
    assertEquals($response['data']['status'], 'active', 'Plugin status should be active');
});

TestSuite::run('PUT /api/v1/plugins/{slug}/status deactivates plugin', function () {
    $output = testController(function () {
        $controller = new PluginManagerController(new TestPdo());
        $request = new TestRequest('{"status":"inactive"}');
        $request->setUser(['roles' => ['admin']]);
        $controller->updatePluginStatus(['slug' => 'clients'], $request);
    });
    
    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Should succeed');
    assertEquals($response['data']['status'], 'inactive', 'Plugin status should be inactive');
});

TestSuite::run('GET /api/v1/plugins rejects non-admin user', function () {
    $output = testController(function () {
        $controller = new PluginManagerController(new TestPdo());
        $request = new TestRequest('');
        $request->setUser(['roles' => ['viewer']]);
        $controller->listPlugins([], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Should fail for non-admin');
    assertEquals(403, $response['error']['code'], 'Error code should be 403');
});

TestSuite::run('GET /api/v1/plugins/updates returns outdated plugins', function () {
    $pdo = new TestPdo();
    $root = createPluginManagerFixture([
        'slug' => 'clients',
        'name' => 'Clients',
        'version' => '2.0.0',
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ]);

    try {
        $loader = new PluginLoader($root, $pdo);
        $controller = new PluginManagerController($pdo, $loader);
        $request = new TestRequest('');
        $request->setUser(['roles' => ['admin']]);

        $output = testController(function () use ($controller, $request): void {
            $controller->listPluginUpdates([], $request);
        });

        $response = json_decode($output, true);
        assertTrue($response['ok'] === true, 'Response ok should be true');
        assertTrue(isset($response['data']['updates']), 'Should have updates array');
        assertEquals(1, count($response['data']['updates']), 'Should return one outdated plugin');
        assertEquals('clients', $response['data']['updates'][0]['slug'], 'Outdated plugin should be clients');
        assertEquals(
            SEMVER_1_0,
            $response['data']['updates'][0]['installed_version'],
            'Installed version should match DB'
        );
        assertEquals(
            '2.0.0',
            $response['data']['updates'][0]['available_version'],
            'Available version should match manifest'
        );
    } finally {
        removePluginManagerFixture($root);
    }
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
