<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/src/bootstrap.php';

// Explicitly require plugin files (not in autoload path)
require_once BASE_PATH . '/plugins/clients/Hooks.php';
require_once BASE_PATH . '/plugins/clients/Installer.php';

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
    assert(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin clients - manifest.json campos requeridos', function (): void {
    $data = json_decode((string) file_get_contents(PLUGIN_DIR . '/manifest.json'), true);
    assert(is_array($data), 'manifest.json must be a JSON object');
    foreach (['slug', 'name', 'version', 'type', 'core_version'] as $field) {
        assert(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assert($data['slug'] === 'clients', 'slug must be clients');
    assert($data['type'] === 'entity', 'type must be entity');
});

TestSuite::run('Plugin clients - schema.json existe', function (): void {
    $path = PLUGIN_DIR . '/schema.json';
    assert(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin clients - schema.json respeta contrato identities/fields/custom_fields/relations', function (): void {
    $data = json_decode((string) file_get_contents(PLUGIN_DIR . '/schema.json'), true);
    assert(is_array($data), 'schema.json must be a JSON object');
    assert(isset($data['identities']) && is_array($data['identities']), 'schema.json must have "identities"');
    assert(isset($data['fields']) && is_array($data['fields']), 'schema.json must have "fields"');
    assert(isset($data['custom_fields']) && is_array($data['custom_fields']), 'schema.json must have "custom_fields"');
    assert(isset($data['relations']) && is_array($data['relations']), 'schema.json must have "relations"');

    assert(isset($data['identities']['id']), 'schema.json must define identity "id"');
    assert(($data['identities']['id']['type'] ?? '') === 'uuid', 'identity id must be uuid');
    assert(($data['identities']['id']['auto_generated'] ?? false) === true, 'identity id must be auto_generated');
    assert(($data['identities']['id']['editable'] ?? true) === false, 'identity id must be non-editable');

    $fieldKeys = array_keys($data['fields']);
    foreach (['nombre', 'apellidos'] as $requiredField) {
        assert(in_array($requiredField, $fieldKeys, true), "schema.json missing field: {$requiredField}");
        assert(($data['fields'][$requiredField]['required'] ?? false) === true, "{$requiredField} must be required");
    }

    $customFieldsByKey = [];
    foreach ($data['custom_fields'] as $customField) {
        assert(is_array($customField), 'each custom_field must be an object');
        assert(isset($customField['key']) && is_string($customField['key']) && $customField['key'] !== '', 'custom_field.key is required');
        assert(isset($customField['type']) && is_string($customField['type']) && $customField['type'] !== '', 'custom_field.type is required');
        assert(isset($customField['required']) && is_bool($customField['required']), 'custom_field.required must be boolean');
        assert(isset($customField['label']) && is_string($customField['label']) && $customField['label'] !== '', 'custom_field.label is required');
        $customFieldsByKey[$customField['key']] = $customField;
    }

    $expectedCustomKeys = ['email', 'telefono', 'creation_stamp', 'activo'];
    $actualCustomKeys = array_keys($customFieldsByKey);
    sort($expectedCustomKeys);
    sort($actualCustomKeys);
    assert($actualCustomKeys === $expectedCustomKeys, 'custom_fields keys must match expected set exactly');

    assert(($customFieldsByKey['email']['type'] ?? '') === 'string', 'email custom_field must be string');
    assert(($customFieldsByKey['email']['required'] ?? true) === false, 'email custom_field must be optional');

    assert(($customFieldsByKey['telefono']['type'] ?? '') === 'string', 'telefono custom_field must be string');
    assert(($customFieldsByKey['telefono']['required'] ?? true) === false, 'telefono custom_field must be optional');

    assert(($customFieldsByKey['creation_stamp']['type'] ?? '') === 'timestamp', 'creation_stamp must be timestamp');
    assert(
        ($customFieldsByKey['creation_stamp']['default'] ?? '') === 'now',
        'creation_stamp default must be "now"'
    );

    assert(($customFieldsByKey['activo']['type'] ?? '') === 'boolean', 'activo custom_field must be boolean');
    assert(($customFieldsByKey['activo']['default'] ?? null) === true, 'activo default must be true');
});

TestSuite::run('Plugin clients - Hooks.php existe', function (): void {
    assert(file_exists(PLUGIN_DIR . '/Hooks.php'), 'Hooks.php not found');
});

TestSuite::run('Hooks - slug no coincide no hace nada', function (): void {
    $pdo   = new ClientsPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'other_entity', 'data' => ['email' => 'x@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $result = $dispatcher->execute('beforeSave', $ctx);
    assert($result['slug'] === 'other_entity', 'ctx should pass through unchanged');
    assert(count($pdo->executedSqls) === 0, 'no SQL should be executed for other entity');
});

TestSuite::run('Hooks - email vacío no ejecuta consulta', function (): void {
    $pdo   = new ClientsPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'clients', 'data' => ['nombre' => 'Test', 'email' => '']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assert($result['data']['email'] === '', 'email should remain empty');
    assert(count($pdo->executedSqls) === 0, 'no SQL for empty email');
});

TestSuite::run('Hooks - email único permite guardar', function (): void {
    $pdo = new ClientsPdoStub();
    $pdo->setFetchColumnReturn(0); // no duplicates
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'clients', 'data' => ['nombre' => 'Test', 'email' => 'nuevo@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assert($result['data']['email'] === 'nuevo@test.com', 'context preserved');
    assert(count($pdo->executedSqls) === 1, 'exactly one query executed');
});

TestSuite::run('Hooks - email duplicado lanza HookException', function (): void {
    $pdo = new ClientsPdoStub();
    $pdo->setFetchColumnReturn(1); // duplicate found
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'clients', 'data' => ['nombre' => 'Test', 'email' => 'dup@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $thrown = false;
    try {
        $dispatcher->execute('beforeSave', $ctx);
    } catch (HookException $e) {
        $thrown = true;
        assert(str_contains($e->getMessage(), 'dup@test.com'), 'message should contain the email');
    }
    assert($thrown, 'HookException must be thrown for duplicate email');
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
    assert(isset($executedParams[':id']), 'id param must be bound when id is present');
    assert($executedParams[':id'] === 'uuid-123', 'id param value is correct');
});

TestSuite::run('Installer - instancia sin errores', function (): void {
    $pdo       = new ClientsPdoStub();
    $installer = new Installer($pdo);
    assert($installer instanceof Installer, 'Installer must instantiate correctly');
});

TestSuite::run('Installer - install() ejecuta INSERT en system_entities y entity_metadata', function (): void {
    $pdo       = new ClientsPdoStub();
    $installer = new Installer($pdo);
    $installer->install();

    $sqls = implode(' ', $pdo->executedSqls);
    assert(str_contains($sqls, 'system_entities'), 'must INSERT into system_entities');
    assert(str_contains($sqls, 'entity_metadata'), 'must INSERT into entity_metadata');
    assert(count($pdo->executedSqls) === 2, 'must execute exactly 2 statements');
});

TestSuite::run('Installer - install() pasa slug correcto', function (): void {
    $pdo       = new ClientsPdoStub();
    $installer = new Installer($pdo);
    $installer->install();

    $params = $pdo->executedParams[0] ?? [];
    assert(($params[':slug'] ?? '') === 'clients', 'slug bound to "clients"');
});

TestSuite::run('Installer - schema sembrado en entity_metadata contiene solo fields', function (): void {
    $pdo       = new ClientsPdoStub();
    $installer = new Installer($pdo);
    $installer->install();

    $params = $pdo->executedParams[1] ?? [];
    $schemaJson = $params[':schema'] ?? null;
    assert(is_string($schemaJson) && $schemaJson !== '', 'installer must bind :schema as non-empty JSON string');

    $decoded = json_decode($schemaJson, true);
    assert(is_array($decoded), 'seeded schema must be valid JSON object');
    assert(isset($decoded['fields']) && is_array($decoded['fields']), 'seeded schema must include fields');
    assert(!isset($decoded['identities']), 'seeded schema must not include identities');
    assert(!isset($decoded['custom_fields']), 'seeded schema must not include custom_fields');
    assert(!isset($decoded['relations']), 'seeded schema must not include relations');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
