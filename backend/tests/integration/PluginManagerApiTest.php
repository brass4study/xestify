<?php

declare(strict_types=1);

/**
 * PluginManagerApiTest — Integration tests for plugin management endpoints.
 *
 * - GET  /api/v1/plugins
 * - GET  /api/v1/plugins/updates
 * - POST /api/v1/plugins/sync
 * - POST /api/v1/plugins/{slug}/update
 * - POST /api/v1/plugins/{slug}/rollback
 * - PUT  /api/v1/plugins/{slug}/status
 * - GET  /api/v1/plugins/{slug}/config
 * - PUT  /api/v1/plugins/{slug}/config
 */

define('BASE_PATH', dirname(__DIR__, 2));
define('SEMVER_1_0', '1.0.0');
define('SEMVER_2_0', '2.0.0');
define('ASSERT_ERR_422', 'Error code should be 422');

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/controllers/PluginManagerController.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/plugins/application/PluginAdministrationService.php';
require_once BASE_PATH . '/src/repositories/PluginRepository.php';
require_once BASE_PATH . '/src/plugins/application/PluginSyncService.php';
require_once BASE_PATH . '/src/plugins/application/PluginOutdatedService.php';
require_once BASE_PATH . '/src/plugins/application/PluginUpdateService.php';
require_once BASE_PATH . '/src/plugins/application/PluginStatusService.php';

use Xestify\controllers\PluginManagerController;
use Xestify\core\Request;
use Xestify\plugins\application\PluginAdministrationService;
use Xestify\plugins\application\PluginOutdatedService;
use Xestify\plugins\application\PluginStatusService;
use Xestify\plugins\application\PluginSyncService;
use Xestify\plugins\application\PluginUpdateService;
use Xestify\repositories\PluginRepository;

final class TestPluginAdministrationService extends PluginAdministrationService
{
    /**
     * @param array<int, array<string, mixed>> $plugins
     * @param array<int, array<string, string>> $updates
     * @param array{
     *   summary?: array<string, int>,
     *   plugins?: array<string, array<string, mixed>>
     * } $syncResult
     * @param array{
     *   plugin?: array<string, mixed>,
     *   update?: array<string, mixed>
     * }|null $updateResult
     * @param array{
     *   plugin?: array<string, mixed>,
     *   rollback?: array<string, mixed>
     * }|null $rollbackResult
     */
    public function __construct(
        private array $plugins,
        private array $updates = [],
        private array $syncResult = ['summary' => [], 'plugins' => []],
        private ?array $updateResult = null,
        private ?Throwable $updateError = null,
        private ?array $rollbackResult = null,
        private ?Throwable $rollbackError = null,
        private ?Throwable $statusError = null
    ) {
        // Parent dependencies are intentionally bypassed in this test double.
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInstalled(): array
    {
        return $this->plugins;
    }

    public function getOutdated(): array
    {
        return $this->updates;
    }

    public function syncAll(): array
    {
        return $this->syncResult;
    }

    public function update(string $slug): array
    {
        if ($this->updateError !== null) {
            throw $this->updateError;
        }

        return $this->updateResult ?? [
            'plugin' => ['slug' => $slug],
            'update' => [
                'from_version' => SEMVER_1_0,
                'to_version' => SEMVER_2_0,
                'schema_changed' => false,
                'diff' => [],
            ],
        ];
    }

    public function rollback(string $slug): array
    {
        if ($this->rollbackError !== null) {
            throw $this->rollbackError;
        }

        return $this->rollbackResult ?? [
            'plugin' => ['slug' => $slug, 'version' => SEMVER_1_0],
            'rollback' => [
                'from_version' => SEMVER_2_0,
                'to_version' => SEMVER_1_0,
                'snapshot_id' => 'snapshot-test-id',
            ],
        ];
    }

    public function setStatus(string $slug, string $status): array
    {
        if ($this->statusError !== null) {
            throw $this->statusError;
        }

        foreach ($this->plugins as &$plugin) {
            if ($plugin['slug'] === $slug) {
                $plugin['status'] = $status;
                return $plugin;
            }
        }

        throw new OutOfBoundsException('missing');
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(string $slug): array
    {
        return $this->setStatus($slug, 'active');
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(string $slug): array
    {
        return $this->setStatus($slug, 'inactive');
    }

    /**
     * @return array{plugin: array<string, mixed>, config: array<string, mixed>}
     */
    public function getConfig(string $slug): array
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin['slug'] === $slug) {
                if (($plugin['status'] ?? '') !== 'active') {
                    throw new DomainException('Only active plugins can be configured.');
                }

                if (($plugin['plugin_type'] ?? '') === 'extension') {
                    return [
                        'plugin' => [
                            'slug' => $plugin['slug'],
                            'name' => $plugin['name'],
                            'plugin_type' => $plugin['plugin_type'],
                            'status' => $plugin['status'],
                            'version' => $plugin['version'],
                            'schema_version' => $plugin['schema_version'],
                        ],
                        'config' => [
                            'target_entity' => '*',
                            'fields' => [
                                [
                                    'active' => true,
                                    'key' => 'body',
                                    'type' => 'text',
                                    'label' => 'Comentario',
                                    'required' => true,
                                    'locked' => false,
                                    'source' => 'base',
                                ],
                            ],
                        ],
                    ];
                }

                return [
                    'plugin' => [
                        'slug' => $plugin['slug'],
                        'name' => $plugin['name'],
                        'plugin_type' => $plugin['plugin_type'],
                        'status' => $plugin['status'],
                        'version' => $plugin['version'],
                        'schema_version' => $plugin['schema_version'],
                    ],
                    'config' => [
                        'fields' => [
                            [
                                'active' => true,
                                'key' => 'name',
                                'type' => 'string',
                                'label' => 'Nombre',
                                'required' => true,
                                'summaryView' => true,
                                'locked' => true,
                                'source' => 'base',
                            ],
                            [
                                'active' => true,
                                'key' => 'phone',
                                'type' => 'string',
                                'label' => 'Telefono',
                                'required' => false,
                                'summaryView' => true,
                                'locked' => false,
                                'source' => 'suggested',
                            ],
                            [
                                'active' => false,
                                'key' => 'is_active',
                                'type' => 'boolean',
                                'label' => 'Activo',
                                'required' => false,
                                'summaryView' => false,
                                'locked' => false,
                                'source' => 'suggested',
                            ],
                        ],
                    ],
                ];
            }
        }

        throw new OutOfBoundsException('missing');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{plugin: array<string, mixed>, config: array<string, mixed>}
     */
    public function saveConfig(string $slug, array $payload): array
    {
        if (!isset($payload['fields']) || !is_array($payload['fields'])) {
            throw new InvalidArgumentException('fields must be an array.');
        }

        foreach ($this->plugins as &$plugin) {
            if ($plugin['slug'] !== $slug) {
                continue;
            }

            if (($plugin['status'] ?? '') !== 'active') {
                throw new DomainException('Only active plugins can be configured.');
            }

            $plugin['schema_version'] = (int) $plugin['schema_version'] + 1;

            $config = ['fields' => $payload['fields']];
            if (($plugin['plugin_type'] ?? '') === 'extension') {
                $config['target_entity'] = (string) ($payload['target_entity'] ?? '*');
            }

            return [
                'plugin' => [
                    'slug' => $plugin['slug'],
                    'name' => $plugin['name'],
                    'plugin_type' => $plugin['plugin_type'],
                    'status' => $plugin['status'],
                    'version' => $plugin['version'],
                    'schema_version' => $plugin['schema_version'],
                ],
                'config' => $config,
            ];
        }

        throw new OutOfBoundsException('missing');
    }
}

final class TestRequest extends Request
{
    public function __construct(string $bodyContent = '')
    {
        parent::__construct(
            body: json_decode($bodyContent, true) ?? [],
            query: [],
            headers: [],
            routeParams: []
        );
    }
}

function testController(callable $fn): string
{
    ob_start();
    $fn();
    return (string) ob_get_clean();
}

function pluginListFixture(): array
{
    return [
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
}

TestSuite::run('GET /api/v1/plugins returns list with all plugins', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(pluginListFixture()));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->listPlugins([], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Response ok should be true');
    assertEquals(2, count($response['data']['plugins']), 'Should return 2 plugins');
});

TestSuite::run('GET /api/v1/plugins returns plugins ordered by slug', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(pluginListFixture()));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->listPlugins([], $request);
    });

    $response = json_decode($output, true);
    assertEquals('clients', $response['data']['plugins'][0]['slug'] ?? null, 'First plugin should be clients');
    assertEquals('comments', $response['data']['plugins'][1]['slug'] ?? null, 'Second plugin should be comments');
});

TestSuite::run('PUT /api/v1/plugins/{slug}/status requires status parameter', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(pluginListFixture()));
    $request = new TestRequest('{}');
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->updatePluginStatus(['slug' => 'clients'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Should fail without status');
    assertEquals(422, $response['error']['code'] ?? null, ASSERT_ERR_422);
});

TestSuite::run('PUT /api/v1/plugins/{slug}/status activates plugin', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(pluginListFixture()));
    $request = new TestRequest('{"status":"active"}');
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->updatePluginStatus(['slug' => 'comments'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Should succeed');
    assertEquals('active', $response['data']['status'] ?? null, 'Plugin status should be active');
});

TestSuite::run('GET /api/v1/plugins rejects non-admin user', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(pluginListFixture()));
    $request = new TestRequest();
    $request->setUser(['roles' => ['viewer']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->listPlugins([], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Should fail for non-admin');
    assertEquals(403, $response['error']['code'] ?? null, 'Error code should be 403');
});

TestSuite::run('GET /api/v1/plugins/updates returns outdated plugins', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(
        pluginListFixture(),
        [[
            'slug' => 'clients',
            'name' => 'Clients',
            'plugin_type' => 'entity',
            'installed_version' => SEMVER_1_0,
            'available_version' => SEMVER_2_0,
        ]]
    ));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->listPluginUpdates([], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Response ok should be true');
    assertEquals(1, count($response['data']['updates']), 'Should return one outdated plugin');
});

TestSuite::run('POST /api/v1/plugins/sync returns sync summary for admin', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(
        pluginListFixture(),
        [],
        [
            'summary' => [
                'discovered' => 2,
                'registered' => 1,
                'unchanged' => 0,
                'outdated' => 1,
                'errors' => 0,
            ],
            'plugins' => [
                'clients' => [
                    'slug' => 'clients',
                    'result' => 'outdated',
                    'installed_version' => SEMVER_1_0,
                    'available_version' => SEMVER_2_0,
                ],
            ],
        ]
    ));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->syncPlugins([], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Sync should succeed for admin');
    assertEquals(2, $response['data']['summary']['discovered'] ?? null, 'Summary should include discovered count');
    assertEquals('outdated', $response['data']['plugins']['clients']['result'] ?? null, 'clients should be outdated');
});

TestSuite::run('POST /api/v1/plugins/{slug}/update returns update result for admin', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(
        pluginListFixture(),
        [],
        ['summary' => [], 'plugins' => []],
        [
            'plugin' => [
                'slug' => 'clients',
                'name' => 'Clients',
                'plugin_type' => 'entity',
                'version' => SEMVER_2_0,
                'status' => 'active',
                'schema_version' => 2,
            ],
            'update' => [
                'from_version' => SEMVER_1_0,
                'to_version' => SEMVER_2_0,
                'schema_changed' => true,
                'diff' => [
                    'fields' => ['added' => ['email']],
                ],
            ],
        ]
    ));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->updatePlugin(['slug' => 'clients'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Update should succeed for admin');
    assertEquals(SEMVER_2_0, $response['data']['plugin']['version'] ?? null, 'Plugin version should be updated');
});

TestSuite::run('POST /api/v1/plugins/{slug}/update returns 404 when plugin is not installed', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(
        pluginListFixture(),
        [],
        ['summary' => [], 'plugins' => []],
        null,
        new OutOfBoundsException('missing')
    ));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->updatePlugin(['slug' => 'missing'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Missing plugin update should fail');
    assertEquals(404, $response['error']['code'] ?? null, 'Error code should be 404');
});

TestSuite::run('POST /api/v1/plugins/{slug}/update returns 409 for unsupported update', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(
        pluginListFixture(),
        [],
        ['summary' => [], 'plugins' => []],
        null,
        new DomainException('non additive change')
    ));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->updatePlugin(['slug' => 'clients'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Unsupported update should fail');
    assertEquals(409, $response['error']['code'] ?? null, 'Error code should be 409');
});

TestSuite::run('POST /api/v1/plugins/{slug}/rollback restores plugin for admin', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(
        pluginListFixture(),
        [],
        ['summary' => [], 'plugins' => []],
        null,
        null,
        [
            'plugin' => [
                'slug' => 'clients',
                'name' => 'Clients',
                'plugin_type' => 'entity',
                'version' => SEMVER_1_0,
                'status' => 'active',
                'schema_version' => 1,
            ],
            'rollback' => [
                'from_version' => SEMVER_2_0,
                'to_version' => SEMVER_1_0,
                'snapshot_id' => 'snapshot-001',
            ],
        ]
    ));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->rollbackPlugin(['slug' => 'clients'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Rollback should succeed for admin');
    assertEquals(SEMVER_1_0, $response['data']['plugin']['version'] ?? null, 'Plugin version should be rolled back');
    assertEquals(SEMVER_2_0, $response['data']['rollback']['from_version'] ?? null, 'from_version should be present');
});

TestSuite::run('POST /api/v1/plugins/{slug}/rollback returns 404 when plugin is not installed', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(
        pluginListFixture(),
        [],
        ['summary' => [], 'plugins' => []],
        null,
        null,
        null,
        new OutOfBoundsException('missing')
    ));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->rollbackPlugin(['slug' => 'missing'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Missing plugin rollback should fail');
    assertEquals(404, $response['error']['code'] ?? null, 'Error code should be 404');
});

TestSuite::run('POST /api/v1/plugins/{slug}/rollback returns 409 when no snapshot exists', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(
        pluginListFixture(),
        [],
        ['summary' => [], 'plugins' => []],
        null,
        null,
        null,
        new DomainException('no snapshot available')
    ));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->rollbackPlugin(['slug' => 'clients'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Rollback without snapshot should fail');
    assertEquals(409, $response['error']['code'] ?? null, 'Error code should be 409');
});

TestSuite::run('GET /api/v1/plugins/{slug}/config returns plugin configuration for active entity', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(pluginListFixture()));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->getPluginConfig(['slug' => 'clients'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Config fetch should succeed');
    assertEquals('clients', $response['data']['plugin']['slug'] ?? null, 'Response should include plugin slug');
    assertTrue(is_array($response['data']['config']['fields'] ?? null), 'fields should be an array');
    assertTrue($response['data']['config']['fields'][0]['summaryView'] === true, 'Base fields should include summaryView');
});

TestSuite::run('GET /api/v1/plugins/{slug}/config returns plugin configuration for active extension', function (): void {
    $plugins = pluginListFixture();
    $plugins[1]['status'] = 'active';
    $controller = new PluginManagerController(new TestPluginAdministrationService($plugins));
    $request = new TestRequest();
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->getPluginConfig(['slug' => 'comments'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Config fetch for extension should succeed');
    assertEquals('comments', $response['data']['plugin']['slug'] ?? null, 'Response should include extension slug');
    assertEquals('*', $response['data']['config']['target_entity'] ?? null, 'Response should include extension target entity');
    assertEquals('base', $response['data']['config']['fields'][0]['source'] ?? null, 'Extension schema fields should be serialized as base');
});

TestSuite::run('PUT /api/v1/plugins/{slug}/config persists extension target_entity', function (): void {
    $plugins = pluginListFixture();
    $plugins[1]['status'] = 'active';
    $controller = new PluginManagerController(new TestPluginAdministrationService($plugins));
    $request = new TestRequest(json_encode([
        'target_entity' => 'clients',
        'fields' => [
            [
                'active' => true,
                'key' => 'body',
                'type' => 'text',
                'label' => 'Comentario',
                'required' => true,
                'summaryView' => true,
            ],
        ],
    ], JSON_UNESCAPED_UNICODE));
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->updatePluginConfig(['slug' => 'comments'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Extension config save should succeed');
    assertEquals('clients', $response['data']['config']['target_entity'] ?? null, 'Extension target_entity should be persisted');
});

TestSuite::run('PUT /api/v1/plugins/{slug}/config updates config and bumps schema version', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(pluginListFixture()));
    $request = new TestRequest(json_encode([
        'fields' => [
            [
                'active' => true,
                'key' => 'name',
                'type' => 'string',
                'label' => 'Nombre',
                'required' => true,
                'summaryView' => true,
                'locked' => true,
                'source' => 'base',
            ],
            [
                'active' => true,
                'key' => 'phone',
                'type' => 'string',
                'label' => 'Telefono',
                'required' => false,
                'summaryView' => false,
                'locked' => false,
                'source' => 'suggested',
            ],
            [
                'active' => true,
                'key' => 'city',
                'type' => 'string',
                'label' => 'Ciudad',
                'required' => false,
                'summaryView' => true,
                'locked' => false,
                'source' => 'additional',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE));
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->updatePluginConfig(['slug' => 'clients'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === true, 'Config save should succeed');
    assertEquals(2, $response['data']['plugin']['schema_version'] ?? null, 'Schema version should be incremented');
    assertEquals('city', $response['data']['config']['fields'][2]['key'] ?? null, 'Additional field should be persisted');
    assertTrue($response['data']['config']['fields'][1]['summaryView'] === false, 'summaryView should round-trip in saved config');
});

TestSuite::run('PUT /api/v1/plugins/{slug}/config validates payload arrays', function (): void {
    $controller = new PluginManagerController(new TestPluginAdministrationService(pluginListFixture()));
    $request = new TestRequest('{}');
    $request->setUser(['roles' => ['admin']]);

    $output = testController(function () use ($controller, $request): void {
        $controller->updatePluginConfig(['slug' => 'clients'], $request);
    });

    $response = json_decode($output, true);
    assertTrue($response['ok'] === false, 'Config save should fail for invalid payload');
    assertEquals(422, $response['error']['code'] ?? null, ASSERT_ERR_422);
});

TestSuite::summary();
exit(TestSuite::exitCode());
