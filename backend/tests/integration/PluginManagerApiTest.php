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

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/controllers/PluginManagerController.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';

use Xestify\controllers\PluginManagerController;
use Xestify\core\Request;

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
            'version' => '1.0.0',
            'status' => 'active',
            'schema_version' => 1,
            'installed_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ],
        [
            'slug' => 'comments',
            'name' => 'Comments',
            'plugin_type' => 'extension',
            'version' => '1.0.0',
            'status' => 'inactive',
            'schema_version' => 1,
            'installed_at' => '2026-01-02T00:00:00+00:00',
            'updated_at' => '2026-01-02T00:00:00+00:00',
        ],
    ];

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        return new TestStatement($this->plugins);
    }

    public function __construct()
    {
        /* stub — no real database connection */
    }
}

class TestStatement
{
    /** @var array<int, array<string, mixed>> */
    private array $plugins;

    /** @var array<string, mixed> */
    private array $lastParams = [];

    public function __construct(array &$plugins)
    {
        $this->plugins = &$plugins;
    }

    public function execute(array $params = []): bool
    {
        $this->lastParams = $params;
        
        // Handle UPDATE: simulate status change
        if (isset($params[':status']) && isset($params[':slug'])) {
            $slug = $params[':slug'];
            foreach ($this->plugins as &$plugin) {
                if ($plugin['slug'] === $slug) {
                    $plugin['status'] = $params[':status'];
                    $plugin['updated_at'] = date('Y-m-d\\TH:i:sP');
                    break;
                }
            }
        }
        
        return true;
    }

    #[\ReturnTypeWillChange]
    public function fetchAll($fetchMode = null, ...$args) // NOSONAR
    {
        return $this->plugins;
    }

    #[\ReturnTypeWillChange]
    public function fetch($fetchMode = null, ...$args) // NOSONAR
    {
        if ($this->lastParams && isset($this->lastParams[':slug'])) {
            $slug = $this->lastParams[':slug'];
            foreach ($this->plugins as $plugin) {
                if ($plugin['slug'] === $slug) {
                    return $plugin;
                }
            }
        }
        return null;
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
    
    $fields = ['slug', 'name', 'plugin_type', 'version', 'status', 'schema_version', 'installed_at', 'updated_at'];
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
        str_contains($response['error']['message'], 'active') || str_contains($response['error']['message'], 'inactive'),
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

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
