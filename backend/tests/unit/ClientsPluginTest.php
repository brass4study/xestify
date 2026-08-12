<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BACKEND_PATH', dirname(__DIR__, 2));
define('BASE_PATH', dirname(BACKEND_PATH));

// Explicitly require plugin files (not in autoload path)
require_once BASE_PATH . '/plugins/clients/Hooks.php';
require_once BASE_PATH . '/plugins/clients/Installer.php';
require_once BACKEND_PATH . '/src/exceptions/HookException.php';
require_once BACKEND_PATH . '/src/exceptions/PluginException.php';
require_once BACKEND_PATH . '/src/plugins/HookDispatcher.php';

require_once __DIR__ . '/helpers.php';

use Xestify\plugins\clients\Hooks;
use Xestify\plugins\clients\Installer;
use Xestify\plugins\HookDispatcher;
use Xestify\exceptions\HookException;
use Xestify\exceptions\PluginException;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

/**
 * PDO stub that records prepared statements and simulates fetchColumn().
 * Avoid calling parent::__construct (requires a real DSN).
 */
class ClientsPdoStub extends PDO
{
    public array $executedSqls    = [];
    public array $executedParams  = [];
    public int   $fetchColumnReturn = 0;

    public function __construct()
    {
        // intentionally no parent call
    }

    public function setFetchColumnReturn(int $value): void
    {
        $this->fetchColumnReturn = $value;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new ClientsStmtStub($this, $query);
    }
}

class ClientsStmtStub extends \PDOStatement
{
    private ClientsPdoStub $pdoStub;
    private string $sql;

    public function __construct(ClientsPdoStub $pdoStub, string $sql)
    {
        $this->pdoStub = $pdoStub;
        $this->sql     = $sql;
    }

    public function execute(?array $params = null): bool
    {
        $this->pdoStub->executedSqls[]   = $this->sql;
        $this->pdoStub->executedParams[] = $params ?? [];
        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->pdoStub->fetchColumnReturn;
    }
}

// ---------------------------------------------------------------------------
// PLUGIN_DIR constant for structure tests
// ---------------------------------------------------------------------------
define('PLUGIN_DIR', BASE_PATH . '/plugins/clients');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('Plugin clients - manifest.json existe', function (): void {
    $path = PLUGIN_DIR . '/manifest.json';
    assertTrue(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin clients - manifest.json campos requeridos', function (): void {
    $data = json_decode((string) file_get_contents(PLUGIN_DIR . '/manifest.json'), true);
    assertTrue(is_array($data), 'manifest.json must be a JSON object');
    foreach (['slug', 'name', 'version', 'type', 'core_version'] as $field) {
        assertTrue(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assertTrue($data['slug'] === 'clients', 'slug must be clients');
    assertTrue($data['type'] === 'entity', 'type must be entity');
});

TestSuite::run('Plugin clients - schema.json existe', function (): void {
    $path = PLUGIN_DIR . '/schema.json';
    assertTrue(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin clients - schema.json respeta contrato identities/fields/custom_fields/relations', function (): void {
    $data = json_decode((string) file_get_contents(PLUGIN_DIR . '/schema.json'), true);
    assertTrue(is_array($data), 'schema.json must be a JSON object');
    assertTrue(isset($data['identities']) && is_array($data['identities']), 'schema.json must have "identities"');
    assertTrue(isset($data['fields']) && is_array($data['fields']), 'schema.json must have "fields"');
    assertTrue(isset($data['custom_fields']) && is_array($data['custom_fields']), 'schema.json must have "custom_fields"');
    assertTrue(isset($data['relations']) && is_array($data['relations']), 'schema.json must have "relations"');

    assertTrue(isset($data['identities']['id']), 'schema.json must define identity "id"');
    assertTrue(($data['identities']['id']['type'] ?? '') === 'uuid', 'identity id must be uuid');
    assertTrue(($data['identities']['id']['auto_generated'] ?? false) === true, 'identity id must be auto_generated');
    assertTrue(($data['identities']['id']['editable'] ?? true) === false, 'identity id must be non-editable');

    $fieldKeys = array_keys($data['fields']);
    foreach (['name', 'email'] as $requiredField) {
        assertTrue(in_array($requiredField, $fieldKeys, true), "schema.json missing field: {$requiredField}");
        assertTrue(($data['fields'][$requiredField]['required'] ?? false) === true, "{$requiredField} must be required");
    }

    $customFieldsByKey = [];
    foreach ($data['custom_fields'] as $customField) {
        assertTrue(is_array($customField), 'each custom_field must be an object');
        assertTrue(isset($customField['key']) && is_string($customField['key']) && $customField['key'] !== '', 'custom_field.key is required');
        assertTrue(isset($customField['type']) && is_string($customField['type']) && $customField['type'] !== '', 'custom_field.type is required');
        assertTrue(isset($customField['required']) && is_bool($customField['required']), 'custom_field.required must be boolean');
        assertTrue(isset($customField['label']) && is_string($customField['label']) && $customField['label'] !== '', 'custom_field.label is required');
        $customFieldsByKey[$customField['key']] = $customField;
    }

    $expectedCustomKeys = ['phone', 'creation_stamp', 'is_active'];
    $actualCustomKeys = array_keys($customFieldsByKey);
    sort($expectedCustomKeys);
    sort($actualCustomKeys);
    assertTrue($actualCustomKeys === $expectedCustomKeys, 'custom_fields keys must match expected set exactly');

    assertTrue(($customFieldsByKey['phone']['type'] ?? '') === 'string', 'phone custom_field must be string');
    assertTrue(($customFieldsByKey['phone']['required'] ?? true) === false, 'phone custom_field must be optional');

    assertTrue(($customFieldsByKey['creation_stamp']['type'] ?? '') === 'timestamp', 'creation_stamp must be timestamp');
    assertTrue(
        ($customFieldsByKey['creation_stamp']['default'] ?? '') === 'now',
        'creation_stamp default must be "now"'
    );

    assertTrue(($customFieldsByKey['is_active']['type'] ?? '') === 'boolean', 'is_active custom_field must be boolean');
    assertTrue(($customFieldsByKey['is_active']['default'] ?? null) === true, 'is_active default must be true');
});

TestSuite::run('Plugin clients - Hooks.php existe', function (): void {
    assertTrue(file_exists(PLUGIN_DIR . '/Hooks.php'), 'Hooks.php not found');
});

TestSuite::run('Hooks - slug no coincide no hace nada', function (): void {
    $pdo   = new ClientsPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'other_entity', 'data' => ['email' => 'x@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $result = $dispatcher->execute('beforeSave', $ctx);
    assertTrue($result['slug'] === 'other_entity', 'ctx should pass through unchanged');
    assertTrue(count($pdo->executedSqls) === 0, 'no SQL should be executed for other entity');
});

TestSuite::run('Hooks - email vacío no ejecuta consulta', function (): void {
    $pdo   = new ClientsPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'clients', 'data' => ['name' => 'Test', 'email' => '']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assertTrue($result['data']['email'] === '', 'email should remain empty');
    assertTrue(count($pdo->executedSqls) === 0, 'no SQL for empty email');
});

TestSuite::run('Hooks - email único permite guardar', function (): void {
    $pdo = new ClientsPdoStub();
    $pdo->setFetchColumnReturn(0); // no duplicates
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'clients', 'data' => ['name' => 'Test', 'email' => 'nuevo@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assertTrue($result['data']['email'] === 'nuevo@test.com', 'context preserved');
    assertTrue(count($pdo->executedSqls) === 1, 'exactly one query executed');
});

TestSuite::run('Hooks - email duplicado lanza HookException', function (): void {
    $pdo = new ClientsPdoStub();
    $pdo->setFetchColumnReturn(1); // duplicate found
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'clients', 'data' => ['name' => 'Test', 'email' => 'dup@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $thrown = false;
    try {
        $dispatcher->execute('beforeSave', $ctx);
    } catch (HookException $e) {
        $thrown = true;
        assertTrue(str_contains($e->getMessage(), 'dup@test.com'), 'message should contain the email');
    }
    assertTrue($thrown, 'HookException must be thrown for duplicate email');
});

TestSuite::run('Hooks - email único en update excluye el propio registro', function (): void {
    $pdo = new ClientsPdoStub();
    $pdo->setFetchColumnReturn(0);
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'clients', 'data' => ['id' => 'uuid-123', 'email' => 'same@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $dispatcher->execute('beforeSave', $ctx);

    $executedParams = $pdo->executedParams[0] ?? [];
    assertTrue(isset($executedParams[':id']), 'id param must be bound when id is present');
    assertTrue($executedParams[':id'] === 'uuid-123', 'id param value is correct');
});

TestSuite::run('Installer - instancia sin errores', function (): void {
    $pdo       = new ClientsPdoStub();
    $installer = new Installer($pdo);
    assertTrue($installer instanceof Installer, 'Installer must instantiate correctly');
});

TestSuite::run('Installer - install() ejecuta operaciones solo sobre plugins', function (): void {
    $pdo       = new ClientsPdoStub();
    $installer = new Installer($pdo);
    $installer->install();

    $sqls = implode(' ', $pdo->executedSqls);
    assertTrue(!str_contains($sqls, 'system_entities'), 'must not depend on system_entities');
    assertTrue(str_contains($sqls, 'plugins'), 'must UPDATE plugins');
    assertTrue(count($pdo->executedSqls) === 2, 'must execute exactly 2 statements');
});

TestSuite::run('Installer - install() pasa slug correcto', function (): void {
    $pdo       = new ClientsPdoStub();
    $installer = new Installer($pdo);
    $installer->install();

    $params = $pdo->executedParams[0] ?? [];
    assertTrue(($params[':slug'] ?? '') === 'clients', 'slug bound to "clients"');
});

TestSuite::run('Installer - schema sembrado en plugins conserva contrato completo', function (): void {
    $pdo       = new ClientsPdoStub();
    $installer = new Installer($pdo);
    $installer->install();

    $params = $pdo->executedParams[1] ?? [];
    $schemaJson = $params[':schema'] ?? null;
    assertTrue(is_string($schemaJson) && $schemaJson !== '', 'installer must bind :schema as non-empty JSON string');

    $decoded = json_decode($schemaJson, true);
    assertTrue(is_array($decoded), 'seeded schema must be valid JSON object');
    assertTrue(isset($decoded['fields']) && is_array($decoded['fields']), 'seeded schema must include fields');
    assertTrue(isset($decoded['identities']) && is_array($decoded['identities']), 'seeded schema must include identities');
    assertTrue(isset($decoded['custom_fields']) && is_array($decoded['custom_fields']), 'seeded schema must include custom_fields');
    assertTrue(isset($decoded['relations']) && is_array($decoded['relations']), 'seeded schema must include relations');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
