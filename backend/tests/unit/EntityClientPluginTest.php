<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/src/bootstrap.php';

// Explicitly require plugin files (not in autoload path)
require_once BASE_PATH . '/plugins/entity_client/Hooks.php';
require_once BASE_PATH . '/plugins/entity_client/Installer.php';

require_once __DIR__ . '/helpers.php';

use Xestify\plugins\entity_client\Hooks;
use Xestify\plugins\entity_client\Installer;
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
class EntityClientPdoStub extends PDO
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
        return new EntityClientStmtStub($this, $query);
    }
}

class EntityClientStmtStub extends \PDOStatement
{
    private EntityClientPdoStub $pdoStub;
    private string $sql;

    public function __construct(EntityClientPdoStub $pdoStub, string $sql)
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
define('PLUGIN_DIR', BASE_PATH . '/plugins/entity_client');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('Plugin entity_client - manifest.json existe', function (): void {
    $path = PLUGIN_DIR . '/manifest.json';
    assert(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin entity_client - manifest.json campos requeridos', function (): void {
    $data = json_decode((string) file_get_contents(PLUGIN_DIR . '/manifest.json'), true);
    assert(is_array($data), 'manifest.json must be a JSON object');
    foreach (['slug', 'name', 'version', 'type', 'core_version'] as $field) {
        assert(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assert($data['slug'] === 'entity_client', 'slug must be entity_client');
    assert($data['type'] === 'entity', 'type must be entity');
});

TestSuite::run('Plugin entity_client - schema.json existe', function (): void {
    $path = PLUGIN_DIR . '/schema.json';
    assert(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin entity_client - schema.json define campos correctos', function (): void {
    $data = json_decode((string) file_get_contents(PLUGIN_DIR . '/schema.json'), true);
    assert(is_array($data) && isset($data['fields']), 'schema.json must have "fields"');
    $fields = array_keys($data['fields']);
    foreach (['nombre', 'email', 'telefono', 'activo'] as $required) {
        assert(in_array($required, $fields, true), "schema.json missing field: {$required}");
    }
    assert($data['fields']['nombre']['required'] === true, 'nombre must be required');
    assert($data['fields']['email']['required'] === true, 'email must be required');
});

TestSuite::run('Plugin entity_client - Hooks.php existe', function (): void {
    assert(file_exists(PLUGIN_DIR . '/Hooks.php'), 'Hooks.php not found');
});

TestSuite::run('Hooks - slug no coincide no hace nada', function (): void {
    $pdo   = new EntityClientPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'other_entity', 'data' => ['email' => 'x@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $result = $dispatcher->execute('beforeSave', $ctx);
    assert($result['slug'] === 'other_entity', 'ctx should pass through unchanged');
    assert(count($pdo->executedSqls) === 0, 'no SQL should be executed for other entity');
});

TestSuite::run('Hooks - email vacío no ejecuta consulta', function (): void {
    $pdo   = new EntityClientPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'client', 'data' => ['nombre' => 'Test', 'email' => '']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assert($result['data']['email'] === '', 'email should remain empty');
    assert(count($pdo->executedSqls) === 0, 'no SQL for empty email');
});

TestSuite::run('Hooks - email único permite guardar', function (): void {
    $pdo = new EntityClientPdoStub();
    $pdo->setFetchColumnReturn(0); // no duplicates
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'client', 'data' => ['nombre' => 'Test', 'email' => 'nuevo@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assert($result['data']['email'] === 'nuevo@test.com', 'context preserved');
    assert(count($pdo->executedSqls) === 1, 'exactly one query executed');
});

TestSuite::run('Hooks - email duplicado lanza HookException', function (): void {
    $pdo = new EntityClientPdoStub();
    $pdo->setFetchColumnReturn(1); // duplicate found
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'client', 'data' => ['nombre' => 'Test', 'email' => 'dup@test.com']];

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
    $pdo = new EntityClientPdoStub();
    $pdo->setFetchColumnReturn(0);
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'client', 'data' => ['id' => 'uuid-123', 'email' => 'same@test.com']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $dispatcher->execute('beforeSave', $ctx);

    $executedParams = $pdo->executedParams[0] ?? [];
    assert(isset($executedParams[':id']), 'id param must be bound when id is present');
    assert($executedParams[':id'] === 'uuid-123', 'id param value is correct');
});

TestSuite::run('Installer - instancia sin errores', function (): void {
    $pdo       = new EntityClientPdoStub();
    $installer = new Installer($pdo);
    assert($installer instanceof Installer, 'Installer must instantiate correctly');
});

TestSuite::run('Installer - install() ejecuta INSERT en system_entities y entity_metadata', function (): void {
    $pdo       = new EntityClientPdoStub();
    $installer = new Installer($pdo);
    $installer->install();

    $sqls = implode(' ', $pdo->executedSqls);
    assert(str_contains($sqls, 'system_entities'), 'must INSERT into system_entities');
    assert(str_contains($sqls, 'entity_metadata'), 'must INSERT into entity_metadata');
    assert(count($pdo->executedSqls) === 2, 'must execute exactly 2 statements');
});

TestSuite::run('Installer - install() pasa slug correcto', function (): void {
    $pdo       = new EntityClientPdoStub();
    $installer = new Installer($pdo);
    $installer->install();

    $params = $pdo->executedParams[0] ?? [];
    assert(($params[':slug'] ?? '') === 'client', 'slug bound to "client"');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
