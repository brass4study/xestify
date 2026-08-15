<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BACKEND_PATH', dirname(__DIR__, 2));
define('BASE_PATH', dirname(BACKEND_PATH));

require_once BACKEND_PATH . '/src/exceptions/HookException.php';
require_once BACKEND_PATH . '/src/core/HookDispatcher.php';
require_once BACKEND_PATH . '/src/plugins/contracts/AbstractUniqueFieldHook.php';

// Explicitly require plugin files (not in autoload path)
require_once BASE_PATH . '/plugins/products/Hooks.php';

require_once __DIR__ . '/helpers.php';

use Xestify\plugins\products\Hooks;
use Xestify\core\HookDispatcher;
use Xestify\exceptions\HookException;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

/**
 * PDO stub that records prepared statements and simulates fetchColumn().
 * Avoid calling parent::__construct (requires a real DSN).
 */
class ProductsPdoStub extends PDO
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
        return new ProductsStmtStub($this, $query);
    }
}

class ProductsStmtStub extends \PDOStatement
{
    private ProductsPdoStub $pdoStub;
    private string $sql;

    public function __construct(ProductsPdoStub $pdoStub, string $sql)
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
define('PRODUCTS_PLUGIN_DIR', BASE_PATH . '/plugins/products');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('Plugin products - manifest.json existe', function (): void {
    $path = PRODUCTS_PLUGIN_DIR . '/manifest.json';
    assertTrue(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin products - manifest.json campos requeridos', function (): void {
    $data = json_decode((string) file_get_contents(PRODUCTS_PLUGIN_DIR . '/manifest.json'), true);
    assertTrue(is_array($data), 'manifest.json must be a JSON object');
    foreach (['name', 'label', 'version', 'type', 'core_version'] as $field) {
        assertTrue(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assertTrue($data['name'] === 'products', 'name must be products');
    assertTrue($data['type'] === 'entity', 'type must be entity');
});

TestSuite::run('Plugin products - schema.json existe', function (): void {
    $path = PRODUCTS_PLUGIN_DIR . '/schema.json';
    assertTrue(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin products - schema.json respeta contrato identities/fields/custom_fields/relations', function (): void {
    $data = json_decode((string) file_get_contents(PRODUCTS_PLUGIN_DIR . '/schema.json'), true);
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
    assertTrue(in_array('name', $fieldKeys, true), 'schema.json missing field: name');
    assertTrue(($data['fields']['name']['required'] ?? false) === true, 'name must be required');

    $customFieldsByKey = [];
    foreach ($data['custom_fields'] as $customField) {
        assertTrue(is_array($customField), 'each custom_field must be an object');
        assertTrue(isset($customField['key']) && is_string($customField['key']) && $customField['key'] !== '', 'custom_field.key is required');
        assertTrue(isset($customField['type']) && is_string($customField['type']) && $customField['type'] !== '', 'custom_field.type is required');
        assertTrue(isset($customField['required']) && is_bool($customField['required']), 'custom_field.required must be boolean');
        assertTrue(isset($customField['label']) && is_string($customField['label']) && $customField['label'] !== '', 'custom_field.label is required');
        $customFieldsByKey[$customField['key']] = $customField;
    }

    $expectedCustomKeys = ['sku', 'description', 'price'];
    $actualCustomKeys = array_keys($customFieldsByKey);
    sort($expectedCustomKeys);
    sort($actualCustomKeys);
    assertTrue($actualCustomKeys === $expectedCustomKeys, 'custom_fields keys must match expected set exactly');

    assertTrue(($customFieldsByKey['sku']['type'] ?? '') === 'string', 'sku custom_field must be string');
    assertTrue(($customFieldsByKey['sku']['required'] ?? false) === true, 'sku custom_field must be required');

    assertTrue(($customFieldsByKey['description']['type'] ?? '') === 'text', 'description custom_field must be text');
    assertTrue(($customFieldsByKey['description']['required'] ?? false) === true, 'description custom_field must be required');

    assertTrue(($customFieldsByKey['price']['type'] ?? '') === 'number', 'price custom_field must be number');
    assertTrue(($customFieldsByKey['price']['required'] ?? true) === false, 'price custom_field must be optional');
});

TestSuite::run('Plugin products - Hooks.php existe', function (): void {
    assertTrue(file_exists(PRODUCTS_PLUGIN_DIR . '/Hooks.php'), 'Hooks.php not found');
});

TestSuite::run('Hooks - plugin_name no coincide no hace nada', function (): void {
    $pdo   = new ProductsPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'other_entity', 'plugin_name' => 'other_plugin', 'data' => ['sku' => 'SKU-1']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $result = $dispatcher->execute('beforeSave', $ctx);
    assertTrue($result['slug'] === 'other_entity', 'ctx should pass through unchanged');
    assertTrue(count($pdo->executedSqls) === 0, 'no SQL should be executed for other plugin');
});

TestSuite::run('Hooks - sku vacío no ejecuta consulta', function (): void {
    $pdo   = new ProductsPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'products', 'plugin_name' => 'products', 'data' => ['name' => 'Test', 'sku' => '']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assertTrue($result['data']['sku'] === '', 'sku should remain empty');
    assertTrue(count($pdo->executedSqls) === 0, 'no SQL for empty sku');
});

TestSuite::run('Hooks - sku único permite guardar', function (): void {
    $pdo = new ProductsPdoStub();
    $pdo->setFetchColumnReturn(0); // no duplicates
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'products', 'plugin_name' => 'products', 'data' => ['name' => 'Test', 'sku' => 'SKU-NEW']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assertTrue($result['data']['sku'] === 'SKU-NEW', 'context preserved');
    assertTrue(count($pdo->executedSqls) === 1, 'exactly one query executed');
});

TestSuite::run('Hooks - sku duplicado lanza HookException', function (): void {
    $pdo = new ProductsPdoStub();
    $pdo->setFetchColumnReturn(1); // duplicate found
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'products', 'plugin_name' => 'products', 'data' => ['name' => 'Test', 'sku' => 'SKU-DUP']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $thrown = false;
    try {
        $dispatcher->execute('beforeSave', $ctx);
    } catch (HookException $e) {
        $thrown = true;
        assertTrue(str_contains($e->getMessage(), 'SKU-DUP'), 'message should contain the sku');
    }
    assertTrue($thrown, 'HookException must be thrown for duplicate sku');
});

TestSuite::run('Hooks - sku único en update excluye el propio registro', function (): void {
    $pdo = new ProductsPdoStub();
    $pdo->setFetchColumnReturn(0);
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'products', 'plugin_name' => 'products', 'data' => ['id' => 'uuid-456', 'sku' => 'SKU-SAME']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $dispatcher->execute('beforeSave', $ctx);

    $executedParams = $pdo->executedParams[0] ?? [];
    assertTrue(isset($executedParams[':id']), 'id param must be bound when id is present');
    assertTrue($executedParams[':id'] === 'uuid-456', 'id param value is correct');
});

TestSuite::run('Hooks - sigue aplicando la unicidad si el slug fue renombrado (STORY 10.3)', function (): void {
    $pdo = new ProductsPdoStub();
    $pdo->setFetchColumnReturn(1); // duplicate found
    $hooks = new Hooks($pdo);
    // plugin_name still 'products' (fixed identity) even though slug was
    // renamed away from the folder name via PluginConfig.
    $ctx   = ['slug' => 'catalogo_b', 'plugin_name' => 'products', 'data' => ['sku' => 'SKU-DUP']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $thrown = false;
    try {
        $dispatcher->execute('beforeSave', $ctx);
    } catch (HookException) {
        $thrown = true;
    }

    assertTrue($thrown, 'Uniqueness must still be enforced after a slug rename');
    $executedParams = $pdo->executedParams[0] ?? [];
    assertTrue(($executedParams[':slug'] ?? '') === 'catalogo_b', 'query must use the live (renamed) slug');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
