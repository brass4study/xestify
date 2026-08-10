<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/controllers/ConfigurationController.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/repositories/ConfigurationRepository.php';

use Xestify\controllers\ConfigurationController;
use Xestify\core\Request;
use Xestify\repositories\ConfigurationRepository;

const ASSERT_RESPONSE_OK = 'Response must be ok';

final class TestConfigurationRepository extends ConfigurationRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $entries = [];

    public function __construct()
    {
        // Test double: no PDO required because persistence is emulated in memory.
    }

    public function all(): array
    {
        return array_values($this->entries);
    }

    public function find(string $key): ?array
    {
        if (!array_key_exists($key, $this->entries)) {
            return null;
        }

        return $this->entries[$key];
    }

    public function save(string $key, array $value): array
    {
        $entry = [
            'key' => $key,
            'value' => $value,
            'created_at' => '2026-08-10T00:00:00+00:00',
            'updated_at' => '2026-08-10T00:00:00+00:00',
        ];
        $this->entries[$key] = $entry;
        return $entry;
    }
}

function callConfigurationController(callable $callback): array
{
    ob_start();
    $callback();
    $output = (string) ob_get_clean();
    $decoded = json_decode($output, true);
    return is_array($decoded) ? $decoded : [];
}

function authenticatedConfigurationRequest(array $user, array $body = []): Request
{
    $request = new Request([], $body, [], []);
    $request->setUser($user);
    return $request;
}

TestSuite::run('index returns all stored configurations for admins', function (): void {
    $repository = new TestConfigurationRepository();
    $repository->save('ui-preferences', ['themeColor' => 'green']);
    $repository->save('other-config', ['enabled' => true]);
    $controller = new ConfigurationController($repository);

    $result = callConfigurationController(static function () use ($controller): void {
        $controller->index([], authenticatedConfigurationRequest(['sub' => 'u1', 'roles' => ['admin']]));
    });

    assertTrue($result['ok'] ?? false, ASSERT_RESPONSE_OK);
    assertEquals(2, count($result['data']['configurations'] ?? []), 'Expected all stored configurations');
});

TestSuite::run('show returns stored ui-preferences for authenticated users', function (): void {
    $repository = new TestConfigurationRepository();
    $repository->save('ui-preferences', ['themeColor' => 'green']);
    $controller = new ConfigurationController($repository);

    $result = callConfigurationController(static function () use ($controller): void {
        $controller->show(
            ['key' => 'ui-preferences'],
            authenticatedConfigurationRequest(['sub' => 'u1', 'roles' => ['operador']])
        );
    });

    assertTrue($result['ok'] ?? false, ASSERT_RESPONSE_OK);
    assertEquals('green', $result['data']['value']['themeColor'] ?? null, 'Theme color mismatch');
});

TestSuite::run('show returns unauthorized when user is missing', function (): void {
    $controller = new ConfigurationController(new TestConfigurationRepository());

    $result = callConfigurationController(static function () use ($controller): void {
        $controller->show(['key' => 'ui-preferences'], new Request());
    });

    assertTrue(!($result['ok'] ?? true), 'Response must fail');
    assertEquals(401, $result['error']['code'] ?? null, 'Expected 401');
});

TestSuite::run('update requires admin role', function (): void {
    $controller = new ConfigurationController(new TestConfigurationRepository());

    $result = callConfigurationController(static function () use ($controller): void {
        $controller->update(
            ['key' => 'ui-preferences'],
            authenticatedConfigurationRequest(['sub' => 'u1', 'roles' => ['operador']], [
                'value' => ['themeColor' => 'amber'],
            ])
        );
    });

    assertTrue(!($result['ok'] ?? true), 'Response must fail');
    assertEquals(403, $result['error']['code'] ?? null, 'Expected 403');
});

TestSuite::run('update persists ui-preferences for admins', function (): void {
    $controller = new ConfigurationController(new TestConfigurationRepository());

    $result = callConfigurationController(static function () use ($controller): void {
        $controller->update(
            ['key' => 'ui-preferences'],
            authenticatedConfigurationRequest(['sub' => 'u1', 'roles' => ['admin']], [
                'value' => ['navigationMode' => 'side'],
            ])
        );
    });

    assertTrue($result['ok'] ?? false, ASSERT_RESPONSE_OK);
    assertEquals('side', $result['data']['value']['navigationMode'] ?? null, 'Navigation mode mismatch');
});

TestSuite::summary();
exit(TestSuite::exitCode());

